<?php

namespace App\Notifications\Orders;

use App\Enums\NotificationCategory;
use App\Enums\NotificationPriority;
use App\Models\Order;
use App\Notifications\RideNotification;

final class NewBusinessOrderNotification extends RideNotification
{
    public function __construct(public Order $order) {}

    public function category(): NotificationCategory
    {
        return NotificationCategory::Business;
    }

    public function title(): string
    {
        return 'Nuevo pedido';
    }

    public function body(): string
    {
        return 'Tienes una nueva comanda #'.$this->order->order_number.'.';
    }

    public function priority(): NotificationPriority
    {
        return NotificationPriority::High;
    }

    public function dedupeKey(): ?string
    {
        return 'business-order:'.$this->order->id;
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
        return '/business/orders/'.$this->order->order_number;
    }
}
