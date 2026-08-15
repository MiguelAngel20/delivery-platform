<?php

namespace App\Actions\Dispatch;

use App\Enums\OrderStatus;
use App\Models\Driver;
use App\Models\Order;
use App\Models\User;
use App\Services\Orders\OrderStateService;
use App\Services\Realtime\OrderRealtimePublisher;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class StartDelivery
{
    public function __construct(
        private readonly OrderStateService $stateService,
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

            if ($locked->order_status !== OrderStatus::PickedUp) {
                throw ValidationException::withMessages([
                    'order_status' => 'Solo puedes iniciar la entrega después de recoger el pedido.',
                ]);
            }

            return $this->stateService->transition(
                $locked,
                OrderStatus::OnTheWay,
                $actor,
                'En camino al cliente',
            );
        });

        if ($previous instanceof OrderStatus) {
            $this->realtime->statusChanged($updated, $previous);
        }

        return $updated;
    }
}
