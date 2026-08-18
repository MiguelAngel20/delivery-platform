<?php

namespace App\Support;

use App\Enums\BusinessOperationMode;
use App\Enums\BusinessUserRole;
use App\Enums\UserRole;
use App\Models\Business;
use App\Models\BusinessBranch;
use App\Models\User;

final class CatalogAccess
{
    public function canManageCatalog(User $user, Business $business): bool
    {
        if ($user->hasRole(UserRole::SystemAdmin)) {
            return true;
        }

        if ($business->operation_mode === BusinessOperationMode::PlatformOperated) {
            return false;
        }

        if (! $user->hasRole(UserRole::BusinessAdmin)) {
            return false;
        }

        return $user->managesBusiness($business);
    }

    public function canManageBranchCatalog(User $user, BusinessBranch $branch): bool
    {
        $business = $branch->business;

        if ($business === null) {
            return false;
        }

        return $this->canManageCatalog($user, $business)
            && $user->canAccessBranch($branch);
    }

    public function canManagePricingRules(User $user, Business $business): bool
    {
        return $user->hasRole(UserRole::SystemAdmin)
            && $business->operation_mode === BusinessOperationMode::PlatformOperated;
    }

    public function isBusinessEmployee(User $user, Business $business): bool
    {
        $membership = $user->activeBusinessMembership($business);

        return $membership !== null
            && $membership->role === BusinessUserRole::BusinessEmployee;
    }
}
