<?php

namespace App\Support;

use App\Models\Order;

final class NotificationPaths
{
    public static function adminOrder(Order $order): string
    {
        return '/admin/orders/'.$order->order_number;
    }
}
