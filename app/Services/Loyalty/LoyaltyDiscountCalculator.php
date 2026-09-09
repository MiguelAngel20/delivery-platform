<?php

namespace App\Services\Loyalty;

use App\Models\Customer;
use App\Models\Order;

/**
 * Resolves the streak reward amount for a customer.
 * Swap implementations later (e.g. distance-based) via the container binding.
 */
interface LoyaltyDiscountCalculator
{
    /**
     * @return numeric-string Absolute currency amount to discount from the service fee.
     */
    public function amountFor(Customer $customer, ?Order $context = null): string;
}
