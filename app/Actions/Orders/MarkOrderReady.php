<?php

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use App\Services\Orders\OrderStateService;
use App\Services\Realtime\OrderRealtimePublisher;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class MarkOrderReady
{
    public function __construct(
        private readonly OrderStateService $stateService,
        private readonly OrderRealtimePublisher $realtime,
    ) {}

    public function handle(Order $order, User $actor): Order
    {
        $previous = null;

        $updated = DB::transaction(function () use ($order, $actor, &$previous): Order {
            /** @var Order $locked */
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $previous = $locked->order_status;

            $allowedFrom = [
                OrderStatus::Preparing,
                OrderStatus::SearchingDriver,
                OrderStatus::DriverAssigned,
                OrderStatus::DriverAtBusiness,
            ];

            if (! in_array($locked->order_status, $allowedFrom, true)) {
                throw ValidationException::withMessages([
                    'order_status' => 'Solo se pueden marcar como listos pedidos en preparación o con repartidor asignado.',
                ]);
            }

            return $this->stateService->transition(
                $locked,
                OrderStatus::ReadyForPickup,
                $actor,
                'Pedido listo para recoger',
                ['ready_at' => now()],
            );
        });

        if ($previous instanceof OrderStatus) {
            $this->realtime->statusChanged($updated, $previous);
        }

        return $updated;
    }
}
