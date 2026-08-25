<?php

namespace App\Notifications\Orders;

use App\Enums\CancelledByType;
use App\Enums\NotificationCategory;
use App\Enums\NotificationPriority;
use App\Enums\UserRole;
use App\Models\Order;
use App\Notifications\RideNotification;

final class OrderCancelledNotification extends RideNotification
{
    public function __construct(
        public Order $order,
        public UserRole $audience,
        public ?CancelledByType $cancelledBy = null,
    ) {}

    public function category(): NotificationCategory
    {
        return NotificationCategory::Order;
    }

    public function title(): string
    {
        return 'Pedido cancelado';
    }

    public function body(): string
    {
        $number = $this->order->order_number;
        $merchant = $this->order->merchantDisplayName();
        $who = $this->cancelledBy?->label();

        $prefix = $merchant !== null
            ? "El pedido #{$number} de {$merchant}"
            : "El pedido #{$number}";

        if ($who === null) {
            return "{$prefix} fue cancelado.";
        }

        return "{$prefix} fue cancelado por {$who}.";
    }

    public function isCritical(): bool
    {
        return true;
    }

    public function requiresPersistentDedupe(): bool
    {
        return true;
    }

    public function priority(): NotificationPriority
    {
        return NotificationPriority::High;
    }

    public function dedupeKey(): ?string
    {
        return sprintf('order:%d:cancelled:%s', $this->order->id, $this->audience->value);
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
