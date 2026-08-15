<?php

namespace App\Services\Orders;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class OrderStateService
{
    /**
     * @var array<string, list<string>>
     */
    private array $allowed = [
        OrderStatus::PendingBusiness->value => [
            OrderStatus::Accepted->value,
            OrderStatus::Preparing->value,
            OrderStatus::Rejected->value,
            OrderStatus::Cancelled->value,
        ],
        OrderStatus::PendingPlatform->value => [
            OrderStatus::Accepted->value,
            OrderStatus::Preparing->value,
            OrderStatus::Rejected->value,
            OrderStatus::Cancelled->value,
            OrderStatus::PendingCustomerConfirmation->value,
        ],
        OrderStatus::PendingCustomerConfirmation->value => [
            OrderStatus::PendingPlatform->value,
            OrderStatus::Cancelled->value,
        ],
        OrderStatus::Accepted->value => [
            OrderStatus::Preparing->value,
            OrderStatus::Cancelled->value,
        ],
        OrderStatus::Preparing->value => [
            OrderStatus::ReadyForPickup->value,
            OrderStatus::SearchingDriver->value,
            OrderStatus::DriverAssigned->value,
            OrderStatus::Cancelled->value,
        ],
        OrderStatus::SearchingDriver->value => [
            OrderStatus::ReadyForPickup->value,
            OrderStatus::DriverAssigned->value,
            OrderStatus::Cancelled->value,
        ],
        OrderStatus::ReadyForPickup->value => [
            OrderStatus::DriverAssigned->value,
            OrderStatus::DriverAtBusiness->value,
            OrderStatus::PickedUp->value,
            OrderStatus::Cancelled->value,
        ],
        OrderStatus::DriverAssigned->value => [
            OrderStatus::DriverAtBusiness->value,
            OrderStatus::ReadyForPickup->value,
            OrderStatus::Cancelled->value,
        ],
        OrderStatus::DriverAtBusiness->value => [
            OrderStatus::ReadyForPickup->value,
            OrderStatus::PickedUp->value,
            OrderStatus::Cancelled->value,
        ],
        OrderStatus::PickedUp->value => [
            OrderStatus::OnTheWay->value,
            OrderStatus::Cancelled->value,
        ],
        OrderStatus::OnTheWay->value => [
            OrderStatus::Delivered->value,
            OrderStatus::Cancelled->value,
        ],
    ];

    public function canTransition(OrderStatus $from, OrderStatus $to): bool
    {
        return in_array($to->value, $this->allowed[$from->value] ?? [], true);
    }

    public function assertCanTransition(OrderStatus $from, OrderStatus $to): void
    {
        if (! $this->canTransition($from, $to)) {
            throw ValidationException::withMessages([
                'order_status' => "Transición inválida de {$from->label()} a {$to->label()}.",
            ]);
        }
    }

    public function transition(
        Order $order,
        OrderStatus $to,
        ?User $actor = null,
        ?string $notes = null,
        array $attributes = [],
    ): Order {
        return DB::transaction(function () use ($order, $to, $actor, $notes, $attributes): Order {
            /** @var Order $locked */
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            $this->assertCanTransition($locked->order_status, $to);

            $locked->fill([
                ...$attributes,
                'order_status' => $to,
            ]);
            $locked->save();

            OrderStatusHistory::query()->create([
                'order_id' => $locked->id,
                'status' => $to,
                'changed_by_user_id' => $actor?->id,
                'notes' => $notes,
                'created_at' => now(),
            ]);

            return $locked->fresh([
                'items.options',
                'addresses',
                'statusHistory',
                'branch.business',
                'customer.user',
                'assignedDriver.user',
            ]);
        });
    }
}
