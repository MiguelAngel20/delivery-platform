<?php

namespace App\Notifications\Orders;

use App\Enums\NotificationCategory;
use App\Enums\NotificationPriority;
use App\Models\Order;
use App\Notifications\RideNotification;

final class PlatformOrderPendingNotification extends RideNotification
{
    public function __construct(public Order $order) {}

    public function category(): NotificationCategory
    {
        return NotificationCategory::Order;
    }

    public function title(): string
    {
        return 'Pedido RIDE pendiente';
    }

    public function body(): string
    {
        return 'Hay un pedido PLATFORM #'.$this->order->order_number.' por confirmar.';
    }

    public function priority(): NotificationPriority
    {
        return NotificationPriority::High;
    }

    public function dedupeKey(): ?string
    {
        return 'platform-order:'.$this->order->id;
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
        return '/admin/orders/'.$this->order->id;
    }
}
