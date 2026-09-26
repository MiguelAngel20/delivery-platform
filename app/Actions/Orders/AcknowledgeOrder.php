<?php

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use App\Services\Orders\OrderStateService;
use App\Services\Realtime\OrderRealtimePublisher;
use Illuminate\Support\Facades\DB;

final class AcknowledgeOrder
{
    public function __construct(
        private readonly OrderStateService $stateService,
        private readonly OrderRealtimePublisher $realtime,
    ) {}

    /**
     * Mark the order as seen by merchant/admin (WhatsApp-style "seen")
     * so the customer knows confirmation started. Idempotent.
     */
    public function handle(Order $order, User $actor): Order
    {
        if (! $order->order_status->isAwaitingMerchantConfirmation()) {
            return $order->fresh([
                'items.options',
                'addresses',
                'statusHistory',
                'branch.business',
                'customer.user',
            ]) ?? $order;
        }

        $previous = $order->order_status;

        $updated = DB::transaction(function () use ($order, $actor): Order {
            /** @var Order $locked */
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if (! $locked->order_status->isAwaitingMerchantConfirmation()) {
                return $locked->fresh([
                    'items.options',
                    'addresses',
                    'statusHistory',
                    'branch.business',
                    'customer.user',
                ]) ?? $locked;
            }

            $this->stateService->assertCanTransition(
                $locked->order_status,
                OrderStatus::Accepted,
            );

            $locked->fill([
                'order_status' => OrderStatus::Accepted,
            ]);
            $locked->save();

            $locked->statusHistory()->create([
                'status' => OrderStatus::Accepted,
                'changed_by_user_id' => $actor->id,
                'notes' => 'Pedido visto. En confirmación.',
                'created_at' => now(),
            ]);

            return $locked->fresh([
                'items.options',
                'addresses',
                'statusHistory',
                'branch.business',
                'customer.user',
            ]);
        });

        if ($updated->order_status === OrderStatus::Accepted && $previous->isAwaitingMerchantConfirmation()) {
            $this->realtime->statusChanged($updated, $previous);
        }

        return $updated;
    }
}
