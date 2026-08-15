<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\OrderQuote;
use App\Models\User;

class OrderQuotePolicy
{
    public function view(User $user, OrderQuote $quote): bool
    {
        if ($user->hasRole(UserRole::SystemAdmin)) {
            return true;
        }

        if ($quote->custom_order_request_id !== null) {
            $quote->loadMissing('customOrderRequest');

            return $user->hasRole(UserRole::Customer)
                && (int) $user->customer?->id === (int) $quote->customOrderRequest?->customer_id;
        }

        if ($quote->order_id !== null) {
            $quote->loadMissing('order');

            return $user->hasRole(UserRole::Customer)
                && (int) $user->customer?->id === (int) $quote->order?->customer_id;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(UserRole::SystemAdmin);
    }

    public function accept(User $user, OrderQuote $quote): bool
    {
        return $this->view($user, $quote) && $user->hasRole(UserRole::Customer);
    }

    public function reject(User $user, OrderQuote $quote): bool
    {
        return $this->accept($user, $quote);
    }
}
