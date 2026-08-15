<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\CustomOrderRequest;
use App\Models\User;

class CustomOrderRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(UserRole::SystemAdmin, UserRole::Customer);
    }

    public function view(User $user, CustomOrderRequest $request): bool
    {
        if ($user->hasRole(UserRole::SystemAdmin)) {
            return true;
        }

        return $user->hasRole(UserRole::Customer)
            && (int) $user->customer?->id === (int) $request->customer_id;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(UserRole::Customer)
            && $user->customer !== null
            && ! $user->customer->isBlocked();
    }

    public function claim(User $user, CustomOrderRequest $request): bool
    {
        return $user->hasRole(UserRole::SystemAdmin);
    }

    public function quote(User $user, CustomOrderRequest $request): bool
    {
        return $user->hasRole(UserRole::SystemAdmin);
    }

    public function reject(User $user, CustomOrderRequest $request): bool
    {
        return $user->hasRole(UserRole::SystemAdmin);
    }

    public function acceptQuote(User $user, CustomOrderRequest $request): bool
    {
        return $this->view($user, $request) && $user->hasRole(UserRole::Customer);
    }

    public function rejectQuote(User $user, CustomOrderRequest $request): bool
    {
        return $this->acceptQuote($user, $request);
    }

    public function cancel(User $user, CustomOrderRequest $request): bool
    {
        if ($user->hasRole(UserRole::SystemAdmin)) {
            return true;
        }

        return $this->acceptQuote($user, $request);
    }
}
