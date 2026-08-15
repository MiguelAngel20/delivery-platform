<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Business;
use App\Models\BusinessUser;
use App\Models\User;

class BusinessUserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(UserRole::SystemAdmin, UserRole::BusinessAdmin);
    }

    public function view(User $user, BusinessUser $businessUser): bool
    {
        if ($user->hasRole(UserRole::SystemAdmin)) {
            return true;
        }

        return $user->managesBusiness($businessUser->business);
    }

    public function create(User $user, ?Business $business = null): bool
    {
        if ($user->hasRole(UserRole::SystemAdmin)) {
            return true;
        }

        if (! $user->hasRole(UserRole::BusinessAdmin) || $business === null) {
            return false;
        }

        return $user->managesBusiness($business);
    }

    public function update(User $user, BusinessUser $businessUser): bool
    {
        return $this->view($user, $businessUser)
            && ($user->hasRole(UserRole::SystemAdmin) || $user->hasRole(UserRole::BusinessAdmin));
    }

    public function deactivate(User $user, BusinessUser $businessUser): bool
    {
        return $this->update($user, $businessUser);
    }

    public function activate(User $user, BusinessUser $businessUser): bool
    {
        return $this->update($user, $businessUser);
    }

    public function assignBranches(User $user, BusinessUser $businessUser): bool
    {
        return $this->update($user, $businessUser);
    }
}
