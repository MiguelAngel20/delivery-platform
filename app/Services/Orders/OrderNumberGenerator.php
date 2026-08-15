<?php

namespace App\Services\Orders;

use App\Models\Order;

final class OrderNumberGenerator
{
    public function next(): string
    {
        $year = now()->format('Y');

        $latest = Order::query()
            ->where('order_number', 'like', "RIDE-{$year}-%")
            ->lockForUpdate()
            ->orderByDesc('order_number')
            ->value('order_number');

        $sequence = 1;

        if (is_string($latest) && preg_match('/RIDE-\d{4}-(\d+)$/', $latest, $matches) === 1) {
            $sequence = ((int) $matches[1]) + 1;
        }

        return sprintf('RIDE-%s-%06d', $year, $sequence);
    }
}
