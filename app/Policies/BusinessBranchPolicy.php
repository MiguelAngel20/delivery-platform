<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Business;
use App\Models\BusinessBranch;
use App\Models\User;

class BusinessBranchPolicy
{
    public function viewAny(User $user, Business $business): bool
    {
        return $user->can('view', $business);
    }

    public function create(User $user, Business $business): bool
    {
        return $user->hasRole(UserRole::SystemAdmin);
    }

    public function update(User $user, BusinessBranch $branch): bool
    {
        if ($user->hasRole(UserRole::SystemAdmin)) {
            return true;
        }

        return $user->hasRole(UserRole::BusinessAdmin)
            && $user->managesBusiness($branch->business);
    }

    public function deactivate(User $user, BusinessBranch $branch): bool
    {
        return $user->hasRole(UserRole::SystemAdmin);
    }

    public function activate(User $user, BusinessBranch $branch): bool
    {
        return $user->hasRole(UserRole::SystemAdmin);
    }
}
