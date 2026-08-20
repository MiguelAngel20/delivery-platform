<?php

namespace App\Notifications\Orders;

use App\Enums\NotificationCategory;
use App\Enums\NotificationPriority;
use App\Models\Order;
use App\Notifications\RideNotification;

final class NewDriverOfferNotification extends RideNotification
{
    public function __construct(public Order $order) {}

    public function category(): NotificationCategory
    {
        return NotificationCategory::Dispatch;
    }

    public function title(): string
    {
        return 'Nuevo pedido';
    }

    public function body(): string
    {
        $name = $this->order->merchantDisplayName() ?: 'un restaurante';
        $minutes = $this->order->estimated_preparation_minutes;

        if ($minutes !== null && $minutes > 0) {
            return "Nuevo pedido en {$name} en {$minutes} minutos.";
        }

        return "Nuevo pedido en {$name}.";
    }

    public function priority(): NotificationPriority
    {
        return NotificationPriority::High;
    }

    public function ttlSeconds(): ?int
    {
        return (int) config('push.ttl.driver_offer_seconds', 120);
    }

    public function dedupeKey(): ?string
    {
        return 'driver-offer:'.$this->order->id;
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
