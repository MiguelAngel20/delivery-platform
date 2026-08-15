<?php

namespace App\Actions\Dispatch;

use App\Enums\DeliveryTripStatus;
use App\Enums\DriverAssignmentStatus;
use App\Enums\OrderStatus;
use App\Models\DeliveryTrip;
use App\Models\DeliveryTripOrder;
use App\Models\Driver;
use App\Models\DriverAssignment;
use App\Models\Order;
use App\Models\User;
use App\Services\Dispatch\DriverActiveOrderService;
use App\Services\Dispatch\DriverEligibilityService;
use App\Services\Orders\OrderStateService;
use App\Services\Realtime\OrderRealtimePublisher;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AcceptDeliveryOrder
{
    public function __construct(
        private readonly DriverEligibilityService $eligibility,
        private readonly DriverActiveOrderService $activeOrders,
        private readonly OrderStateService $stateService,
        private readonly OrderRealtimePublisher $realtime,
    ) {}

    public function handle(Order $order, Driver $driver, User $actor): Order
    {
        $previous = null;

        $updated = DB::transaction(function () use ($order, $driver, $actor, &$previous): Order {
            /** @var Order $locked */
            $locked = Order::query()
                ->with(['branch.business'])
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();
            $previous = $locked->order_status;

            /** @var Driver $lockedDriver */
            $lockedDriver = Driver::query()
                ->with(['user', 'businesses'])
                ->whereKey($driver->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->assigned_driver_id !== null) {
                throw ValidationException::withMessages([
                    'order' => 'Este pedido ya fue tomado por otro repartidor.',
                ]);
            }

            $this->eligibility->assertEligible($lockedDriver, $locked);

            $now = now();

            DriverAssignment::query()->create([
                'order_id' => $locked->id,
                'driver_id' => $lockedDriver->id,
                'status' => DriverAssignmentStatus::Accepted,
                'offered_at' => $now,
                'accepted_at' => $now,
            ]);

            $locked->forceFill([
                'assigned_driver_id' => $lockedDriver->id,
            ])->save();

            if ($locked->order_status !== OrderStatus::DriverAssigned) {
                $this->stateService->assertCanTransition(
                    $locked->order_status,
                    OrderStatus::DriverAssigned,
                );

                $locked->forceFill([
                    'order_status' => OrderStatus::DriverAssigned,
                ])->save();

                $locked->statusHistory()->create([
                    'status' => OrderStatus::DriverAssigned,
                    'changed_by_user_id' => $actor->id,
                    'notes' => 'Repartidor asignado',
                    'created_at' => $now,
                ]);
            }

            $this->attachToTrip($locked, $lockedDriver);
            $this->activeOrders->markBusy($lockedDriver);

            return $locked->fresh([
                'items.options',
                'addresses',
                'statusHistory',
                'branch.business',
                'customer.user',
                'assignedDriver.user',
            ]);
        });

        if ($previous instanceof OrderStatus) {
            $this->realtime->driverAssigned($updated, $previous);
        }

        return $updated;
    }

    private function attachToTrip(Order $order, Driver $driver): void
    {
        $order->loadMissing('branch');

        if ($order->branch === null) {
            return;
        }

        $trip = $this->activeOrders->openTripForBranch($driver, $order->branch_id);

        if ($trip === null) {
            $trip = DeliveryTrip::query()->create([
                'driver_id' => $driver->id,
                'business_id' => $order->branch->business_id,
                'branch_id' => $order->branch_id,
                'status' => DeliveryTripStatus::Open,
                'started_at' => now(),
            ]);
        } elseif ($trip->status === DeliveryTripStatus::Open) {
            $trip->forceFill([
                'status' => DeliveryTripStatus::InProgress,
            ])->save();
        }

        $existing = DeliveryTripOrder::query()
            ->where('order_id', $order->id)
            ->lockForUpdate()
            ->first();

        if ($existing !== null) {
            throw ValidationException::withMessages([
                'order' => 'El pedido ya pertenece a un viaje.',
            ]);
        }

        $sequence = (int) DeliveryTripOrder::query()
            ->where('delivery_trip_id', $trip->id)
            ->max('sequence') + 1;

        DeliveryTripOrder::query()->create([
            'delivery_trip_id' => $trip->id,
            'order_id' => $order->id,
            'sequence' => $sequence,
        ]);
    }
}
