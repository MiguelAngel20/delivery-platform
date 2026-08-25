<?php

namespace App\Services\Incidents;

use App\Enums\IncidentSeverity;
use App\Enums\IncidentStatus;
use App\Enums\IncidentType;
use App\Events\Incidents\IncidentCreated;
use App\Models\Incident;
use App\Models\Order;
use App\Models\User;
use App\Services\Notifications\RideNotificationDispatcher;
use App\Services\Reputation\ReputationRecalculator;
use App\Support\SafeBroadcast;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class IncidentService
{
    public function __construct(
        private readonly ReputationRecalculator $reputation,
        private readonly RideNotificationDispatcher $notifications,
    ) {}

    /**
     * @param  array{
     *     type: IncidentType,
     *     description: string,
     *     severity?: IncidentSeverity,
     *     status?: IncidentStatus,
     *     idempotency_key?: string|null
     * }  $payload
     */
    public function report(Order $order, User $actor, array $payload): Incident
    {
        $order->loadMissing(['branch.business', 'assignedDriver', 'customer']);

        $type = $payload['type'];
        $description = trim($payload['description']);

        if ($description === '') {
            throw ValidationException::withMessages([
                'description' => 'Describe el problema.',
            ]);
        }

        $severity = $payload['severity'] ?? $this->defaultSeverity($type);
        $status = $payload['status'] ?? IncidentStatus::Open;
        $idempotencyKey = $payload['idempotency_key'] ?? null;

        if ($idempotencyKey !== null) {
            $existing = Incident::query()->where('idempotency_key', $idempotencyKey)->first();

            if ($existing !== null) {
                return $existing;
            }
        }

        $openDuplicate = Incident::query()
            ->where('order_id', $order->id)
            ->where('type', $type)
            ->whereIn('status', [IncidentStatus::Open->value, IncidentStatus::UnderReview->value])
            ->first();

        if ($openDuplicate !== null) {
            return $openDuplicate;
        }

        try {
            $incident = DB::transaction(function () use ($order, $actor, $type, $severity, $status, $description, $idempotencyKey): Incident {
                /** @var Order $locked */
                $locked = Order::query()
                    ->with('branch')
                    ->whereKey($order->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($idempotencyKey !== null) {
                    $existing = Incident::query()->where('idempotency_key', $idempotencyKey)->first();

                    if ($existing !== null) {
                        return $existing;
                    }
                }

                $duplicate = Incident::query()
                    ->where('order_id', $locked->id)
                    ->where('type', $type)
                    ->whereIn('status', [IncidentStatus::Open->value, IncidentStatus::UnderReview->value])
                    ->first();

                if ($duplicate !== null) {
                    return $duplicate;
                }

                return Incident::query()->create([
                    'order_id' => $locked->id,
                    'customer_id' => $locked->customer_id,
                    'driver_id' => $locked->assigned_driver_id,
                    'business_id' => $locked->branch?->business_id ?? $order->branch?->business_id,
                    'reported_by_user_id' => $actor->id,
                    'type' => $type,
                    'severity' => $severity,
                    'status' => $status,
                    'description' => $description,
                    'idempotency_key' => $idempotencyKey,
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            if ($idempotencyKey !== null) {
                return Incident::query()
                    ->where('idempotency_key', $idempotencyKey)
                    ->firstOrFail();
            }

            return Incident::query()
                ->where('order_id', $order->id)
                ->where('type', $type)
                ->whereIn('status', [IncidentStatus::Open->value, IncidentStatus::UnderReview->value])
                ->firstOrFail();
        }

        // Already existed (idempotent return from inner transaction) — skip side effects.
        if (! $incident->wasRecentlyCreated) {
            return $incident;
        }

        $this->broadcastCreated($incident->fresh(['order.branch.business', 'reportedBy']));

        if ($incident->order !== null) {
            $this->reputation->forOrder($incident->order);
        }

        return $incident;
    }

    public function resolve(Incident $incident, User $actor, string $resolution): Incident
    {
        $resolution = trim($resolution);

        if ($resolution === '') {
            throw ValidationException::withMessages([
                'resolution' => 'Indica la resolución.',
            ]);
        }

        if (in_array($incident->status, [IncidentStatus::Resolved, IncidentStatus::Closed], true)) {
            throw ValidationException::withMessages([
                'status' => 'Esta incidencia ya fue cerrada.',
            ]);
        }

        $incident->forceFill([
            'status' => IncidentStatus::Resolved,
            'resolution' => $resolution,
            'resolved_by_user_id' => $actor->id,
            'resolved_at' => now(),
        ])->save();

        $fresh = $incident->fresh(['order', 'reportedBy', 'resolvedBy']);

        if ($fresh?->order !== null) {
            $this->reputation->forOrder($fresh->order);
        }

        return $fresh;
    }

    public function markUnderReview(Incident $incident): Incident
    {
        if ($incident->status === IncidentStatus::Open) {
            $incident->forceFill([
                'status' => IncidentStatus::UnderReview,
            ])->save();
        }

        return $incident->fresh();
    }

    private function defaultSeverity(IncidentType $type): IncidentSeverity
    {
        return match ($type) {
            IncidentType::SafetyIncident => IncidentSeverity::Critical,
            IncidentType::CancellationReview, IncidentType::OrderDamaged => IncidentSeverity::High,
            IncidentType::DriverProblem, IncidentType::PaymentProblem => IncidentSeverity::Medium,
            default => IncidentSeverity::Medium,
        };
    }

    private function broadcastCreated(Incident $incident): void
    {
        $payload = [
            'incident_id' => $incident->id,
            'order_id' => $incident->order_id,
            'order_number' => $incident->order?->order_number,
            'type' => $incident->type->value,
            'severity' => $incident->severity->value,
            'status' => $incident->status->value,
        ];

        $callback = function () use ($payload, $incident): void {
            SafeBroadcast::event(new IncidentCreated($payload, ['admin']));
            $this->notifications->incidentCreated($incident);
        };

        if (DB::transactionLevel() > 0) {
            DB::afterCommit($callback);

            return;
        }

        $callback();
    }
}
