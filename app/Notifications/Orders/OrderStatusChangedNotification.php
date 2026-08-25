<?php

namespace App\Notifications\Orders;

use App\Enums\NotificationCategory;
use App\Enums\NotificationPriority;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Notifications\RideNotification;

final class OrderStatusChangedNotification extends RideNotification
{
    public function __construct(
        public Order $order,
        public OrderStatus $status,
        public UserRole $audience,
    ) {}

    public function category(): NotificationCategory
    {
        return NotificationCategory::Order;
    }

    public function title(): string
    {
        if ($this->audience === UserRole::Customer) {
            return match ($this->status) {
                OrderStatus::Accepted, OrderStatus::Preparing => 'Tu pedido fue aceptado',
                OrderStatus::PickedUp, OrderStatus::OnTheWay => 'Tu pedido va en camino',
                OrderStatus::Delivered => 'Pedido entregado',
                default => 'Actualización de pedido',
            };
        }

        return match ($this->status) {
            OrderStatus::ReadyForPickup => 'Pedido listo para recoger',
            OrderStatus::Cancelled => 'Pedido cancelado',
            OrderStatus::Rejected => 'Pedido rechazado',
            default => 'Actualización de pedido',
        };
    }

    public function body(): string
    {
        $number = $this->order->order_number;
        $minutes = $this->order->estimated_preparation_minutes;

        if ($this->audience === UserRole::Customer) {
            return match ($this->status) {
                OrderStatus::Accepted, OrderStatus::Preparing => $minutes !== null && $minutes > 0
                    ? "Tiempo estimado: {$minutes} minutos."
                    : 'El establecimiento ya está preparando tu pedido.',
                OrderStatus::PickedUp, OrderStatus::OnTheWay => 'Tu pedido fue recolectado y ya va en camino.',
                OrderStatus::Delivered => 'Tu pedido fue entregado.',
                default => "Tu pedido #{$number} cambió de estado.",
            };
        }

        return match ($this->status) {
            OrderStatus::ReadyForPickup => "El pedido #{$number} está listo para recoger.",
            OrderStatus::Cancelled => "El pedido #{$number} fue cancelado.",
            OrderStatus::Rejected => "El pedido #{$number} fue rechazado.",
            default => "El pedido #{$number} cambió de estado.",
        };
    }

    public function isCritical(): bool
    {
        return in_array($this->status, [
            OrderStatus::Cancelled,
            OrderStatus::Rejected,
            OrderStatus::OnTheWay,
            OrderStatus::PickedUp,
            OrderStatus::Delivered,
        ], true);
    }

    public function requiresPersistentDedupe(): bool
    {
        if ($this->audience !== UserRole::Customer) {
            return false;
        }

        return in_array($this->status, [
            OrderStatus::Accepted,
            OrderStatus::Preparing,
            OrderStatus::PickedUp,
            OrderStatus::OnTheWay,
            OrderStatus::Delivered,
        ], true);
    }

    public function priority(): NotificationPriority
    {
        return $this->isCritical()
            ? NotificationPriority::High
            : NotificationPriority::Normal;
    }

    public function dedupeKey(): ?string
    {
        if ($this->audience === UserRole::Customer) {
            return match ($this->status) {
                OrderStatus::Accepted, OrderStatus::Preparing => 'order:'.$this->order->id.':accepted',
                OrderStatus::PickedUp, OrderStatus::OnTheWay => 'order:'.$this->order->id.':en-camino',
                OrderStatus::Delivered => 'order:'.$this->order->id.':delivered',
                default => sprintf('order:%d:status:%s:customer', $this->order->id, $this->status->value),
            };
        }

        return sprintf('order:%d:status:%s:%s', $this->order->id, $this->status->value, $this->audience->value);
    }

    public function targetType(): ?string
    {
        return 'order';
    }

    public function targetId(): ?int
    {
        return $this->order->id;
    }

    public function clickPath(): ?string
    {
        return match ($this->audience) {
            UserRole::Customer => '/customer/orders/'.$this->order->order_number,
            UserRole::Driver => '/driver/orders',
            UserRole::BusinessAdmin, UserRole::BusinessEmployee => '/business/orders/'.$this->order->order_number,
            UserRole::SystemAdmin => '/admin/orders/'.$this->order->id,
        };
    }
}
