<?php

namespace App\Services\Reputation;

use App\Enums\CancellationResponsibility;
use App\Enums\DriverAssignmentStatus;
use App\Enums\IncidentType;
use App\Enums\OrderStatus;
use App\Events\Reputation\DriverRated;
use App\Models\Customer;
use App\Models\Driver;
use App\Models\DriverMetric;
use App\Models\DriverRating;
use App\Models\Incident;
use App\Models\Order;
use App\Models\OrderCancellation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class DriverReputationService
{
    /**
     * @return array<string, mixed>
     */
    public function config(): array
    {
        return config('reputation.driver');
    }

    public function recalculate(Driver $driver): DriverMetric
    {
        $facts = $this->collectFacts($driver);
        $score = $this->scoreFromFacts($facts);

        $metrics = DriverMetric::query()->firstOrNew(['driver_id' => $driver->id]);
        $metrics->forceFill([
            ...$facts,
            'trust_score' => $score,
            'last_recalculated_at' => now(),
        ])->save();

        return $metrics->fresh();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function rate(Order $order, Customer $customer, array $payload): DriverRating
    {
        if ($order->order_status !== OrderStatus::Delivered) {
            throw ValidationException::withMessages([
                'order' => 'Solo puedes calificar pedidos entregados.',
            ]);
        }

        if ((int) $order->customer_id !== (int) $customer->id) {
            throw ValidationException::withMessages([
                'order' => 'Este pedido no te pertenece.',
            ]);
        }

        if ($order->assigned_driver_id === null) {
            throw ValidationException::withMessages([
                'driver' => 'Este pedido no tiene repartidor asignado.',
            ]);
        }

        $existing = DriverRating::query()
            ->where('order_id', $order->id)
            ->where('driver_id', $order->assigned_driver_id)
            ->where('customer_id', $customer->id)
            ->first();

        if ($existing !== null) {
            throw ValidationException::withMessages([
                'order' => 'Ya calificaste este servicio.',
            ]);
        }

        $rating = DB::transaction(function () use ($order, $customer, $payload): DriverRating {
            return DriverRating::query()->create([
                'order_id' => $order->id,
                'driver_id' => $order->assigned_driver_id,
                'customer_id' => $customer->id,
                'overall_rating' => $payload['overall_rating'],
                'speed_rating' => $payload['speed_rating'] ?? null,
                'service_rating' => $payload['service_rating'] ?? null,
                'care_rating' => $payload['care_rating'] ?? null,
                'respect_rating' => $payload['respect_rating'] ?? null,
                'communication_rating' => $payload['communication_rating'] ?? null,
                'comment' => $payload['comment'] ?? null,
            ]);
        });

        $driver = $order->assignedDriver ?? Driver::query()->findOrFail($order->assigned_driver_id);
        $metrics = $this->recalculate($driver);

        $this->broadcastRated(
            $rating->fresh(['driver.user', 'order']),
            $metrics->average_rating,
        );

        return $rating;
    }

    /**
     * @return array{
     *     offered_orders: int,
     *     accepted_orders: int,
     *     rejected_orders: int,
     *     completed_orders: int,
     *     cancelled_orders: int,
     *     responsible_cancellations: int,
     *     incident_count: int,
     *     responsible_incidents: int,
     *     average_rating: float|null,
     *     total_ratings: int
     * }
     */
    public function collectFacts(Driver $driver): array
    {
        $driver->loadMissing(['assignments', 'ratings']);

        $offered = $driver->assignments->count();
        $accepted = $driver->assignments
            ->whereIn('status', [DriverAssignmentStatus::Accepted, DriverAssignmentStatus::Cancelled])
            ->count();
        $rejected = $driver->assignments->where('status', DriverAssignmentStatus::Rejected)->count();

        $completed = Order::query()
            ->where('assigned_driver_id', $driver->id)
            ->where('order_status', OrderStatus::Delivered)
            ->count();

        $cancelledOrders = Order::query()
            ->where('assigned_driver_id', $driver->id)
            ->where('order_status', OrderStatus::Cancelled)
            ->count();

        $responsibleCancellations = OrderCancellation::query()
            ->where('responsibility', CancellationResponsibility::Driver)
            ->whereHas('order', function ($query) use ($driver): void {
                $query->where(function ($inner) use ($driver): void {
                    $inner->where('assigned_driver_id', $driver->id)
                        ->orWhereHas(
                            'assignments',
                            fn ($assignments) => $assignments->where('driver_id', $driver->id),
                        );
                });
            })
            ->count();

        $incidents = Incident::query()->where('driver_id', $driver->id)->get();
        $responsibleIncidents = $incidents
            ->filter(fn (Incident $incident): bool => $this->isDriverResponsibleIncident($incident))
            ->count();

        $ratings = $driver->ratings;
        $totalRatings = $ratings->count();
        $average = $totalRatings > 0
            ? round((float) $ratings->avg('overall_rating'), 2)
            : null;

        return [
            'offered_orders' => $offered,
            'accepted_orders' => $accepted,
            'rejected_orders' => $rejected,
            'completed_orders' => $completed,
            'cancelled_orders' => $cancelledOrders,
            'responsible_cancellations' => $responsibleCancellations,
            'incident_count' => $incidents->count(),
            'responsible_incidents' => $responsibleIncidents,
            'average_rating' => $average,
            'total_ratings' => $totalRatings,
        ];
    }

    /**
     * @param  array<string, mixed>  $facts
     */
    public function scoreFromFacts(array $facts): float
    {
        $cfg = $this->config();
        $points = $cfg['points'];
        $score = (float) $cfg['base_score']
            + ((int) $facts['completed_orders'] * (float) $points['completed_order'])
            + ((int) $facts['responsible_cancellations'] * (float) $points['responsible_cancellation'])
            + ((int) $facts['responsible_incidents'] * (float) $points['responsible_incident']);

        if ($facts['average_rating'] !== null && (int) $facts['total_ratings'] > 0) {
            $score += (((float) $facts['average_rating'] - 3) * (float) $points['rating_delta_weight']);
        }

        return round(
            max((float) $cfg['min_score'], min((float) $cfg['max_score'], $score)),
            2,
        );
    }

    public function qualityLabel(?float $score): ?string
    {
        if ($score === null) {
            return null;
        }

        foreach ($this->config()['quality_levels'] as $level) {
            if ($score >= (float) $level['min']) {
                return $level['label'];
            }
        }

        return null;
    }

    private function isDriverResponsibleIncident(Incident $incident): bool
    {
        if (in_array($incident->type, [
            IncidentType::DriverProblem,
            IncidentType::SafetyIncident,
        ], true)) {
            return true;
        }

        if ($incident->type !== IncidentType::CancellationReview) {
            return false;
        }

        $incident->loadMissing('order.cancellation');

        return $incident->order?->cancellation?->responsibility === CancellationResponsibility::Driver;
    }

    private function broadcastRated(DriverRating $rating, mixed $averageRating): void
    {
        $payload = [
            'rating_id' => $rating->id,
            'driver_id' => $rating->driver_id,
            'order_id' => $rating->order_id,
            'overall_rating' => $rating->overall_rating,
            'average_rating' => $averageRating,
        ];

        $callback = static function () use ($payload, $rating): void {
            broadcast(new DriverRated($payload, [
                'driver.'.$rating->driver_id,
                'admin',
            ]));
        };

        if (DB::transactionLevel() > 0) {
            DB::afterCommit($callback);

            return;
        }

        $callback();
    }
}
