<?php

namespace App\Notifications\Orders;

use App\Enums\NotificationCategory;
use App\Enums\NotificationPriority;
use App\Models\Order;
use App\Notifications\RideNotification;

final class DriverOrderReadyNotification extends RideNotification
{
    public function __construct(public Order $order) {}

    public function category(): NotificationCategory
    {
        return NotificationCategory::Dispatch;
    }

    public function title(): string
    {
        return 'Pedido listo para recoger';
    }

    public function body(): string
    {
        $name = $this->order->merchantDisplayName() ?: 'el restaurante';

        return "El pedido #{$this->order->order_number} de {$name} está listo para recoger.";
    }

    public function priority(): NotificationPriority
    {
        return NotificationPriority::High;
    }

    public function dedupeKey(): ?string
    {
        return 'driver-ready:'.$this->order->id;
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
        return '/driver/orders';
    }
}
