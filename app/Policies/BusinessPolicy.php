<?php

namespace App\Policies;

use App\Enums\BusinessUserStatus;
use App\Enums\UserRole;
use App\Models\Business;
use App\Models\User;

class BusinessPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(UserRole::SystemAdmin);
    }

    public function view(User $user, Business $business): bool
    {
        if ($user->hasRole(UserRole::SystemAdmin)) {
            return true;
        }

        return $this->belongsToBusiness($user, $business);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(UserRole::SystemAdmin);
    }

    public function update(User $user, Business $business): bool
    {
        if ($user->hasRole(UserRole::SystemAdmin)) {
            return true;
        }

        return $user->hasRole(UserRole::BusinessAdmin)
            && $this->belongsToBusiness($user, $business);
    }

    public function approve(User $user, Business $business): bool
    {
        return $user->hasRole(UserRole::SystemAdmin);
    }

    public function reject(User $user, Business $business): bool
    {
        return $user->hasRole(UserRole::SystemAdmin);
    }

    public function suspend(User $user, Business $business): bool
    {
        return $user->hasRole(UserRole::SystemAdmin);
    }

    public function activate(User $user, Business $business): bool
    {
        return $user->hasRole(UserRole::SystemAdmin);
    }

    private function belongsToBusiness(User $user, Business $business): bool
    {
        return $business->memberships()
            ->where('user_id', $user->id)
            ->where('status', BusinessUserStatus::Active)
            ->exists();
    }
}
