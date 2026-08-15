<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Business;
use App\Models\BusinessUpgradeRequest;
use App\Models\User;

class BusinessUpgradeRequestPolicy
{
    public function viewAny(User $user, ?Business $business = null): bool
    {
        if ($user->hasRole(UserRole::SystemAdmin)) {
            return true;
        }

        return $business !== null
            && $user->hasRole(UserRole::BusinessAdmin)
            && $user->managesBusiness($business);
    }

    public function create(User $user, Business $business): bool
    {
        return $user->hasRole(UserRole::BusinessAdmin)
            && $user->managesBusiness($business);
    }

    public function review(User $user, BusinessUpgradeRequest $request): bool
    {
        return $user->hasRole(UserRole::SystemAdmin);
    }
}
