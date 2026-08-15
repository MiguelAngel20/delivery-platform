<?php

namespace App\Services\Orders;

use App\Enums\CancellationReasonCode;
use App\Enums\CancellationResponsibility;
use App\Enums\CancellationReviewStatus;
use App\Enums\CancelledByType;
use App\Enums\DriverAssignmentStatus;
use App\Enums\IncidentSeverity;
use App\Enums\IncidentStatus;
use App\Enums\IncidentType;
use App\Enums\OrderStatus;
use App\Models\DeliveryTripOrder;
use App\Models\Driver;
use App\Models\DriverAssignment;
use App\Models\Order;
use App\Models\OrderCancellation;
use App\Models\User;
use App\Services\Dispatch\DriverActiveOrderService;
use App\Services\Finance\OrderFinancialService;
use App\Services\Incidents\IncidentService;
use App\Services\Realtime\OrderRealtimePublisher;
use App\Services\Reputation\ReputationRecalculator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class OrderCancellationService
{
    public function __construct(
        private readonly OrderStateService $stateService,
        private readonly OrderFinancialService $financials,
        private readonly IncidentService $incidents,
        private readonly DriverActiveOrderService $activeOrders,
        private readonly OrderRealtimePublisher $realtime,
        private readonly ReputationRecalculator $reputation,
    ) {}

    public function customerCanCancel(Order $order): bool
    {
        return in_array($order->order_status, $this->customerCancellableStatuses(), true);
    }

    public function customerCanReportProblem(Order $order): bool
    {
        return $order->order_status->isActiveForCustomer() && ! $this->customerCanCancel($order);
    }

    public function businessCanCancel(Order $order): bool
    {
        return in_array($order->order_status, $this->businessCancellableStatuses(), true);
    }

    public function adminCanCancel(Order $order): bool
    {
        return ! $order->order_status->isTerminal();
    }

    /**
     * @return list<OrderStatus>
     */
    public function customerCancellableStatuses(): array
    {
        return [
            OrderStatus::PendingBusiness,
            OrderStatus::PendingPlatform,
            OrderStatus::PendingCustomerConfirmation,
            OrderStatus::Accepted,
            OrderStatus::Preparing,
            OrderStatus::SearchingDriver,
            OrderStatus::ReadyForPickup,
            OrderStatus::DriverAssigned,
            OrderStatus::DriverAtBusiness,
        ];
    }

    /**
     * @return list<OrderStatus>
     */
    public function businessCancellableStatuses(): array
    {
        return [
            OrderStatus::Accepted,
            OrderStatus::Preparing,
            OrderStatus::SearchingDriver,
            OrderStatus::ReadyForPickup,
            OrderStatus::DriverAssigned,
            OrderStatus::DriverAtBusiness,
        ];
    }

    public function cancelByCustomer(
        Order $order,
        User $actor,
        CancellationReasonCode $reasonCode,
        ?string $reason = null,
    ): Order {
        if (! $this->customerCanCancel($order)) {
            throw ValidationException::withMessages([
                'order' => $order->picked_up_at !== null || in_array($order->order_status, [
                    OrderStatus::PickedUp,
                    OrderStatus::OnTheWay,
                ], true)
                    ? 'Ya no puedes cancelar este pedido. Reporta un problema para que lo revise un administrador.'
                    : 'Este pedido ya no se puede cancelar.',
            ]);
        }

        return $this->cancel(
            $order,
            $actor,
            CancelledByType::Customer,
            $reasonCode,
            $reason,
        );
    }

    public function cancelByBusiness(
        Order $order,
        User $actor,
        CancellationReasonCode $reasonCode,
        ?string $reason = null,
    ): Order {
        if (! $this->businessCanCancel($order)) {
            throw ValidationException::withMessages([
                'order' => 'Este pedido no se puede cancelar en su estado actual.',
            ]);
        }

        return $this->cancel(
            $order,
            $actor,
            CancelledByType::Business,
            $reasonCode,
            $reason,
        );
    }

    public function cancelByAdmin(
        Order $order,
        User $actor,
        CancellationReasonCode $reasonCode,
        ?string $reason = null,
        ?CancellationResponsibility $responsibility = null,
    ): Order {
        if (! $this->adminCanCancel($order)) {
            throw ValidationException::withMessages([
                'order' => 'No se puede cancelar un pedido finalizado.',
            ]);
        }

        return $this->cancel(
            $order,
            $actor,
            CancelledByType::SystemAdmin,
            $reasonCode,
            $reason,
            $responsibility,
        );
    }

    public function review(
        OrderCancellation $cancellation,
        User $actor,
        CancellationResponsibility $responsibility,
        ?string $notes = null,
    ): OrderCancellation {
        if ($responsibility === CancellationResponsibility::UnderReview) {
            throw ValidationException::withMessages([
                'responsibility' => 'Selecciona un responsable final.',
            ]);
        }

        $cancellation->forceFill([
            'responsibility' => $responsibility,
            'review_status' => CancellationReviewStatus::Resolved,
            'reviewed_by_user_id' => $actor->id,
            'review_notes' => $notes,
            'reviewed_at' => now(),
        ])->save();

        $fresh = $cancellation->fresh(['order', 'cancelledBy', 'reviewedBy']);

        if ($fresh?->order !== null) {
            $this->reputation->forOrder($fresh->order);
        }

        return $fresh;
    }

    public function driverCannotContinue(
        Order $order,
        Driver $driver,
        User $actor,
        CancellationReasonCode $reasonCode,
        string $description,
    ): Order {
        $previous = null;

        $updated = DB::transaction(function () use ($order, $driver, $actor, $reasonCode, $description, &$previous): Order {
            /** @var Order $locked */
            $locked = Order::query()
                ->with(['branch.business', 'financial', 'financialTransactions', 'tripOrder.trip'])
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $locked->assigned_driver_id !== (int) $driver->id) {
                throw ValidationException::withMessages([
                    'order' => 'No eres el repartidor asignado a este pedido.',
                ]);
            }

            if ($locked->order_status->isTerminal()) {
                throw ValidationException::withMessages([
                    'order' => 'Este pedido ya está cerrado.',
                ]);
            }

            $pickedUp = in_array($locked->order_status, [
                OrderStatus::PickedUp,
                OrderStatus::OnTheWay,
            ], true);

            $this->incidents->report($locked, $actor, [
                'type' => IncidentType::DriverProblem,
                'description' => $description !== '' ? $description : $reasonCode->label(),
                'severity' => $pickedUp ? IncidentSeverity::High : IncidentSeverity::Medium,
                'status' => $pickedUp ? IncidentStatus::UnderReview : IncidentStatus::Open,
                'idempotency_key' => "order:{$locked->id}:driver-cannot-continue",
            ]);

            if ($pickedUp) {
                $this->financials->markRequiresReview($locked);

                return $locked->fresh([
                    'items.options',
                    'addresses',
                    'statusHistory',
                    'branch.business',
                    'customer.user',
                    'assignedDriver.user',
                    'cancellation',
                    'incidents',
                ]);
            }

            $previous = $locked->order_status;

            DriverAssignment::query()
                ->where('order_id', $locked->id)
                ->where('driver_id', $driver->id)
                ->where('status', DriverAssignmentStatus::Accepted)
                ->update([
                    'status' => DriverAssignmentStatus::Cancelled,
                    'cancelled_at' => now(),
                ]);

            $tripOrder = $locked->tripOrder;
            $trip = $tripOrder?->trip;
            $tripOrder?->delete();

            $nextStatus = $locked->ready_at !== null
                ? OrderStatus::ReadyForPickup
                : OrderStatus::Preparing;

            $locked->forceFill([
                'assigned_driver_id' => null,
                'driver_arrived_at' => null,
                'order_status' => $nextStatus,
            ])->save();

            $locked->statusHistory()->create([
                'status' => $nextStatus,
                'changed_by_user_id' => $actor->id,
                'notes' => 'Repartidor no puede continuar: '.$reasonCode->label(),
                'created_at' => now(),
            ]);

            if ($trip !== null) {
                $this->activeOrders->completeTripIfFinished($trip->fresh('orders'));
            }

            $this->activeOrders->maybeMarkAvailable($driver->fresh());

            return $locked->fresh([
                'items.options',
                'addresses',
                'statusHistory',
                'branch.business',
                'customer.user',
                'assignedDriver.user',
                'incidents',
            ]);
        });

        if ($previous instanceof OrderStatus) {
            $this->realtime->statusChanged($updated, $previous);
        }

        $this->reputation->forOrder($updated);

        return $updated;
    }

    private function cancel(
        Order $order,
        User $actor,
        CancelledByType $byType,
        CancellationReasonCode $reasonCode,
        ?string $reason = null,
        ?CancellationResponsibility $forcedResponsibility = null,
    ): Order {
        $previous = null;

        $updated = DB::transaction(function () use (
            $order,
            $actor,
            $byType,
            $reasonCode,
            $reason,
            $forcedResponsibility,
            &$previous,
        ): Order {
            /** @var Order $locked */
            $locked = Order::query()
                ->with(['financial', 'financialTransactions', 'branch.business', 'assignedDriver', 'tripOrder.trip'])
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->cancellation !== null || OrderCancellation::query()->where('order_id', $locked->id)->exists()) {
                throw ValidationException::withMessages([
                    'order' => 'Este pedido ya tiene una cancelación registrada.',
                ]);
            }

            if ($locked->order_status->isTerminal()) {
                throw ValidationException::withMessages([
                    'order' => 'El pedido ya fue cerrado y no se puede cancelar.',
                ]);
            }

            $this->stateService->assertCanTransition($locked->order_status, OrderStatus::Cancelled);

            $previous = $locked->order_status;
            $hadMovements = $this->financials->hasCompletedMovements($locked);
            $afterPickup = in_array($previous, [OrderStatus::PickedUp, OrderStatus::OnTheWay], true)
                || $locked->picked_up_at !== null;

            [$responsibility, $reviewStatus] = $this->resolveResponsibility(
                $byType,
                $reasonCode,
                $previous,
                $forcedResponsibility,
            );

            $locked->forceFill([
                'order_status' => OrderStatus::Cancelled,
            ])->save();

            $locked->statusHistory()->create([
                'status' => OrderStatus::Cancelled,
                'changed_by_user_id' => $actor->id,
                'notes' => $reason ?: $reasonCode->label(),
                'created_at' => now(),
            ]);

            OrderCancellation::query()->create([
                'order_id' => $locked->id,
                'cancelled_by_user_id' => $actor->id,
                'cancelled_by_type' => $byType,
                'reason_code' => $reasonCode,
                'reason' => $reason,
                'previous_order_status' => $previous,
                'responsibility' => $responsibility,
                'review_status' => $reviewStatus,
                'cancelled_at' => now(),
            ]);

            if ($hadMovements || $afterPickup) {
                $this->financials->markRequiresReview($locked);
            }

            if ($afterPickup || $reviewStatus === CancellationReviewStatus::Pending) {
                $this->incidents->report($locked, $actor, [
                    'type' => IncidentType::CancellationReview,
                    'description' => 'Cancelación que requiere revisión administrativa. Motivo: '.$reasonCode->label()
                        .($reason ? '. '.$reason : ''),
                    'severity' => $afterPickup ? IncidentSeverity::High : IncidentSeverity::Medium,
                    'status' => IncidentStatus::UnderReview,
                    'idempotency_key' => "order:{$locked->id}:cancellation-review",
                ]);
            }

            $this->releaseDriverIfAssigned($locked);

            return $locked->fresh([
                'items.options',
                'addresses',
                'statusHistory',
                'branch.business',
                'customer.user',
                'assignedDriver.user',
                'cancellation',
                'financial',
                'financialTransactions',
                'incidents',
            ]);
        });

        if ($previous instanceof OrderStatus) {
            $this->realtime->statusChanged($updated, $previous);
        }

        $this->reputation->forOrder($updated);

        return $updated;
    }

    /**
     * @return array{0: CancellationResponsibility, 1: CancellationReviewStatus}
     */
    private function resolveResponsibility(
        CancelledByType $byType,
        CancellationReasonCode $reasonCode,
        OrderStatus $previous,
        ?CancellationResponsibility $forced,
    ): array {
        if ($forced !== null && $forced !== CancellationResponsibility::UnderReview) {
            return [$forced, CancellationReviewStatus::NotRequired];
        }

        if ($byType === CancelledByType::Customer && $previous->isEarlyCustomerCancelWindow()) {
            return [CancellationResponsibility::Customer, CancellationReviewStatus::NotRequired];
        }

        if ($byType === CancelledByType::Business && $reasonCode->impliesBusinessResponsibility()) {
            return [CancellationResponsibility::Business, CancellationReviewStatus::NotRequired];
        }

        if (in_array($previous, [OrderStatus::PickedUp, OrderStatus::OnTheWay], true)) {
            return [CancellationResponsibility::UnderReview, CancellationReviewStatus::Pending];
        }

        if ($byType === CancelledByType::Customer && ! $previous->isEarlyCustomerCancelWindow()) {
            return [CancellationResponsibility::UnderReview, CancellationReviewStatus::Pending];
        }

        if ($byType === CancelledByType::SystemAdmin) {
            return [CancellationResponsibility::UnderReview, CancellationReviewStatus::Pending];
        }

        return [CancellationResponsibility::UnderReview, CancellationReviewStatus::Pending];
    }

    private function releaseDriverIfAssigned(Order $order): void
    {
        if ($order->assigned_driver_id === null) {
            return;
        }

        $driver = $order->assignedDriver;

        DriverAssignment::query()
            ->where('order_id', $order->id)
            ->where('status', DriverAssignmentStatus::Accepted)
            ->update([
                'status' => DriverAssignmentStatus::Cancelled,
                'cancelled_at' => now(),
            ]);

        $tripOrder = DeliveryTripOrder::query()->with('trip')->where('order_id', $order->id)->first();
        $trip = $tripOrder?->trip;
        $tripOrder?->delete();

        if ($trip !== null) {
            $this->activeOrders->completeTripIfFinished($trip->fresh('orders'));
        }

        if ($driver !== null) {
            $this->activeOrders->maybeMarkAvailable($driver->fresh());
        }
    }
}
