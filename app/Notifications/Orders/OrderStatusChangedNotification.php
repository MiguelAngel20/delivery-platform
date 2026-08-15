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
        return match ($this->status) {
            OrderStatus::Accepted, OrderStatus::Preparing => 'Tu pedido fue aceptado',
            OrderStatus::DriverAssigned => 'Repartidor asignado',
            OrderStatus::PickedUp => 'Pedido recogido',
            OrderStatus::OnTheWay => 'Tu pedido va en camino',
            OrderStatus::Delivered => 'Pedido entregado',
            OrderStatus::Cancelled => 'Pedido cancelado',
            OrderStatus::Rejected => 'Pedido rechazado',
            OrderStatus::ReadyForPickup => 'Pedido listo',
            OrderStatus::PendingCustomerConfirmation => 'Confirma el nuevo total',
            default => 'Actualización de pedido',
        };
    }

    public function body(): string
    {
        $number = $this->order->order_number;

        return match ($this->status) {
            OrderStatus::Accepted, OrderStatus::Preparing => 'El establecimiento ya está preparando tu pedido.',
            OrderStatus::DriverAssigned => 'Tu pedido ya tiene repartidor.',
            OrderStatus::PickedUp => "El pedido #{$number} fue recogido.",
            OrderStatus::OnTheWay => 'El repartidor ya salió hacia tu ubicación.',
            OrderStatus::Delivered => 'Esperamos que todo haya llegado bien.',
            OrderStatus::Cancelled => "El pedido #{$number} fue cancelado.",
            OrderStatus::Rejected => "El pedido #{$number} fue rechazado.",
            OrderStatus::ReadyForPickup => "El pedido #{$number} está listo para recoger.",
            OrderStatus::PendingCustomerConfirmation => 'Revisa y confirma el total actualizado.',
            default => "Tu pedido #{$number} cambió de estado.",
        };
    }

    public function isCritical(): bool
    {
        return in_array($this->status, [
            OrderStatus::Cancelled,
            OrderStatus::Rejected,
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
