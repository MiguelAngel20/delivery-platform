<?php

namespace App\Actions\Dispatch;

use App\Enums\OrderStatus;
use App\Models\Driver;
use App\Models\Order;
use App\Models\User;
use App\Services\Finance\OrderFinancialService;
use App\Services\Orders\OrderStateService;
use App\Services\Realtime\OrderRealtimePublisher;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PickupOrder
{
    public function __construct(
        private readonly OrderStateService $stateService,
        private readonly OrderFinancialService $financials,
        private readonly OrderRealtimePublisher $realtime,
    ) {}

    public function handle(Order $order, Driver $driver, User $actor): Order
    {
        $previous = null;

        $updated = DB::transaction(function () use ($order, $driver, $actor, &$previous): Order {
            /** @var Order $locked */
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $previous = $locked->order_status;

            if ((int) $locked->assigned_driver_id !== (int) $driver->id) {
                throw ValidationException::withMessages([
                    'order' => 'No eres el repartidor asignado a este pedido.',
                ]);
            }

            $ready = $locked->ready_at !== null;
            $arrived = $locked->driver_arrived_at !== null;

            $canPickup = ($locked->order_status === OrderStatus::DriverAtBusiness && $ready)
                || ($locked->order_status === OrderStatus::ReadyForPickup && $arrived && $ready);

            if (! $canPickup) {
                throw ValidationException::withMessages([
                    'order_status' => 'Debes llegar al establecimiento y el pedido debe estar listo.',
                ]);
            }

            $this->stateService->assertCanTransition($locked->order_status, OrderStatus::PickedUp);

            $locked->forceFill([
                'order_status' => OrderStatus::PickedUp,
                'picked_up_at' => now(),
            ])->save();

            $locked->statusHistory()->create([
                'status' => OrderStatus::PickedUp,
                'changed_by_user_id' => $actor->id,
                'notes' => 'Pedido recogido',
                'created_at' => now(),
            ]);

            $this->financials->recordPickupPayment($locked->fresh(['branch.business', 'financial']), $driver, $actor);

            return $locked->fresh([
                'items.options',
                'addresses',
                'statusHistory',
                'branch.business',
                'customer.user',
                'assignedDriver.user',
                'financial',
                'financialTransactions',
                'payment',
            ]);
        });

        if ($previous instanceof OrderStatus) {
            $this->realtime->statusChanged($updated, $previous);
        }

        return $updated;
    }
}
