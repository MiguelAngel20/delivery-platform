<?php

namespace App\Support;

use App\Enums\OrderStatus;
use App\Models\Order;

final class OrderBroadcastPayload
{
    /**
     * @return array<string, mixed>
     */
    public static function base(Order $order): array
    {
        $order->loadMissing(['branch.business', 'assignedDriver.user']);

        return [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'status' => $order->order_status->value,
            'status_label' => $order->order_status->label(),
            'branch_id' => $order->branch_id,
            'business_id' => $order->branch?->business_id,
            'customer_id' => $order->customer_id,
            'assigned_driver_id' => $order->assigned_driver_id,
            'assigned_driver_name' => $order->assignedDriver?->user?->name,
            'estimated_preparation_minutes' => $order->estimated_preparation_minutes,
            'operation_mode' => $order->operation_mode->value,
            'updated_at' => $order->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function withPrevious(Order $order, OrderStatus $previous): array
    {
        return [
            ...self::base($order),
            'previous_status' => $previous->value,
        ];
    }
}
