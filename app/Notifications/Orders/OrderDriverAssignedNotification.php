<?php

namespace App\Notifications\Orders;

use App\Enums\NotificationCategory;
use App\Enums\NotificationPriority;
use App\Models\Order;
use App\Notifications\RideNotification;

final class OrderDriverAssignedNotification extends RideNotification
{
    public function __construct(public Order $order) {}

    public function category(): NotificationCategory
    {
        return NotificationCategory::Order;
    }

    public function title(): string
    {
        return 'Repartidor asignado';
    }

    public function body(): string
    {
        $name = $this->order->assignedDriver?->user?->name;

        if ($name !== null && $name !== '') {
            return "{$name} irá por tu pedido #{$this->order->order_number}.";
        }

        return "Ya asignamos un repartidor a tu pedido #{$this->order->order_number}.";
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
        return 'order:'.$this->order->id.':driver-assigned';
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
        return '/customer/orders/'.$this->order->order_number;
    }
}
