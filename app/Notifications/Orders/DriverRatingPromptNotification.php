<?php

namespace App\Notifications\Orders;

use App\Enums\NotificationCategory;
use App\Models\Order;
use App\Notifications\RideNotification;

final class DriverRatingPromptNotification extends RideNotification
{
    public function __construct(public Order $order) {}

    public function category(): NotificationCategory
    {
        return NotificationCategory::Order;
    }

    public function title(): string
    {
        return '¿Cómo estuvo tu repartidor?';
    }

    public function body(): string
    {
        return 'Califica tu entrega.';
    }

    public function dedupeKey(): ?string
    {
        return 'rating-prompt:'.$this->order->id;
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
