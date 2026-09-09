<?php

namespace App\Services\Orders;

use App\Models\Order;

final class OrderNumberGenerator
{
    private const PREFIX = 'CHIS';

    public function next(): string
    {
        $year = now()->format('Y');

        $latestSequence = Order::query()
            ->where(function ($query) use ($year): void {
                $query->where('order_number', 'like', self::PREFIX."-{$year}-%")
                    ->orWhere('order_number', 'like', "RIDE-{$year}-%");
            })
            ->lockForUpdate()
            ->pluck('order_number')
            ->map(function (string $orderNumber): int {
                if (preg_match('/(?:CHIS|RIDE)-\d{4}-(\d+)$/', $orderNumber, $matches) !== 1) {
                    return 0;
                }

                return (int) $matches[1];
            })
            ->max() ?? 0;

        return sprintf('%s-%s-%06d', self::PREFIX, $year, $latestSequence + 1);
    }
}
