<?php

namespace App\Notifications\Orders;

use App\Enums\NotificationCategory;
use App\Enums\NotificationPriority;
use App\Models\Order;
use App\Notifications\RideNotification;

final class AdminAffiliateOrderNotification extends RideNotification
{
    public function __construct(public Order $order) {}

    public function category(): NotificationCategory
    {
        return NotificationCategory::Order;
    }

    public function title(): string
    {
        return 'Pedido de afiliado';
    }

    public function body(): string
    {
        $name = $this->order->merchantDisplayName() ?: 'un afiliado';

        return "Nuevo pedido #{$this->order->order_number} en {$name} con repartidores RIDE.";
    }

    public function priority(): NotificationPriority
    {
        return NotificationPriority::High;
    }

    public function dedupeKey(): ?string
    {
        return 'admin-affiliate-order:'.$this->order->id;
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
