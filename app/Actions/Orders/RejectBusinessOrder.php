<?php

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use App\Services\Orders\OrderStateService;
use App\Services\Realtime\OrderRealtimePublisher;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RejectBusinessOrder
{
    public function __construct(
        private readonly OrderStateService $stateService,
        private readonly OrderRealtimePublisher $realtime,
    ) {}

    public function handle(Order $order, User $actor, string $reason): Order
    {
        if (blank($reason)) {
            throw ValidationException::withMessages([
                'reason' => 'Indica el motivo del rechazo.',
            ]);
        }

        $previous = $order->order_status;

        $updated = DB::transaction(function () use ($order, $actor, $reason): Order {
            /** @var Order $locked */
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if (! $locked->order_status->isAwaitingMerchantConfirmation()) {
                throw ValidationException::withMessages([
                    'order_status' => 'El pedido ya fue procesado.',
                ]);
            }

            return $this->stateService->transition(
                $locked,
                OrderStatus::Rejected,
                $actor,
                $reason,
            );
        });

        $this->realtime->statusChanged($updated, $previous);

        return $updated;
    }
}
