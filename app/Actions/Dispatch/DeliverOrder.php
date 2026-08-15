<?php

namespace App\Actions\Dispatch;

use App\Enums\OrderStatus;
use App\Models\DeliveryTripOrder;
use App\Models\Driver;
use App\Models\Order;
use App\Models\User;
use App\Services\Dispatch\DriverActiveOrderService;
use App\Services\Finance\OrderFinancialService;
use App\Services\Orders\OrderStateService;
use App\Services\Realtime\OrderRealtimePublisher;
use App\Services\Reputation\ReputationRecalculator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class DeliverOrder
{
    public function __construct(
        private readonly OrderStateService $stateService,
        private readonly DriverActiveOrderService $activeOrders,
        private readonly OrderFinancialService $financials,
        private readonly OrderRealtimePublisher $realtime,
        private readonly ReputationRecalculator $reputation,
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

            if ($locked->order_status !== OrderStatus::OnTheWay) {
                throw ValidationException::withMessages([
                    'order_status' => 'Solo puedes entregar pedidos en camino.',
                ]);
            }

            $delivered = $this->stateService->transition(
                $locked,
                OrderStatus::Delivered,
                $actor,
                'Pedido entregado',
                ['delivered_at' => now()],
            );

            $tripOrder = DeliveryTripOrder::query()
                ->with('trip')
                ->where('order_id', $delivered->id)
                ->first();

            if ($tripOrder?->trip !== null) {
                $this->activeOrders->completeTripIfFinished($tripOrder->trip->fresh('orders'));
            }

            $this->activeOrders->maybeMarkAvailable($driver->fresh());

            $this->financials->recordCustomerCollection(
                $delivered->fresh(['financial', 'payment', 'branch.business']),
                $driver,
                $actor,
            );

            return $delivered->fresh([
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

        $this->reputation->forOrder($updated);

        return $updated;
    }
}
