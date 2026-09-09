<?php

namespace App\Services\Loyalty;

use App\Models\Customer;
use App\Models\Order;

/**
 * Current calculator: random amount in [min, max] when the streak unlocks.
 * Replace with a distance-based calculator later without changing loyalty flow.
 */
final class UnlockRangeLoyaltyDiscountCalculator implements LoyaltyDiscountCalculator
{
    public function amountFor(Customer $customer, ?Order $context = null): string
    {
        $min = (int) config('business.loyalty.streak.discount_min', 15);
        $max = (int) config('business.loyalty.streak.discount_max', 20);

        if ($max < $min) {
            [$min, $max] = [$max, $min];
        }

        return number_format((float) random_int($min, $max), 2, '.', '');
    }
}
