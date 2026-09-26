<?php

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use App\Services\Orders\OrderStateService;
use App\Services\Realtime\OrderRealtimePublisher;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AcceptBusinessOrder
{
    public function __construct(
        private readonly AcknowledgeOrder $acknowledge,
        private readonly OrderStateService $stateService,
        private readonly OrderRealtimePublisher $realtime,
    ) {}

    public function handle(Order $order, User $actor, int $estimatedPreparationMinutes): Order
    {
        if ($estimatedPreparationMinutes < 1 || $estimatedPreparationMinutes > 180) {
            throw ValidationException::withMessages([
                'estimated_preparation_minutes' => 'Indica un tiempo de preparación válido.',
            ]);
        }

        if ($order->order_status->isAwaitingMerchantConfirmation()) {
            $order = $this->acknowledge->handle($order, $actor);
        }

        $previous = $order->order_status;

        $updated = DB::transaction(function () use ($order, $actor, $estimatedPreparationMinutes): Order {
            /** @var Order $locked */
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($locked->order_status !== OrderStatus::Accepted) {
                throw ValidationException::withMessages([
                    'order_status' => 'El pedido ya fue procesado.',
                ]);
            }

            $this->stateService->assertCanTransition(
                $locked->order_status,
                OrderStatus::Preparing,
            );

            $now = now();

            $locked->fill([
                'order_status' => OrderStatus::Preparing,
                'estimated_preparation_minutes' => $estimatedPreparationMinutes,
                'business_accepted_at' => $now,
                'preparation_started_at' => $now,
            ]);
            $locked->save();

            $locked->statusHistory()->create([
                'status' => OrderStatus::Preparing,
                'changed_by_user_id' => $actor->id,
                'notes' => "Aceptado. Tiempo estimado: {$estimatedPreparationMinutes} min",
                'created_at' => $now,
            ]);

            return $locked->fresh([
                'items.options',
                'addresses',
                'statusHistory',
                'branch.business',
                'customer.user',
            ]);
        });

        $this->realtime->statusChanged($updated, $previous);

        return $updated;
    }
}
