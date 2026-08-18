<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Business;
use App\Models\BusinessUser;
use App\Models\User;
use App\Support\BusinessAccess;

class BusinessUserPolicy
{
    public function __construct(
        private readonly BusinessAccess $businessAccess,
    ) {}

    public function viewAny(User $user): bool
    {
        return $user->hasRole(UserRole::SystemAdmin, UserRole::BusinessAdmin);
    }

    public function view(User $user, BusinessUser $businessUser): bool
    {
        if ($user->hasRole(UserRole::SystemAdmin)) {
            return true;
        }

        return $user->managesBusiness($businessUser->business)
            && $this->sharesBranchScope($user, $businessUser);
    }

    public function create(User $user, ?Business $business = null): bool
    {
        if ($user->hasRole(UserRole::SystemAdmin)) {
            return true;
        }

        if (! $user->hasRole(UserRole::BusinessAdmin) || $business === null) {
            return false;
        }

        return $user->managesBusiness($business)
            && $this->businessAccess->accessibleBranchIds($user, $business) !== [];
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

    private function sharesBranchScope(User $user, BusinessUser $businessUser): bool
    {
        $business = $businessUser->business;

        if ($business === null) {
            return false;
        }

        if (! $businessUser->branches()->exists()) {
            return $user->managesBusiness($business);
        }

        $accessibleBranchIds = $this->businessAccess->accessibleBranchIds($user, $business);

        if ($accessibleBranchIds === []) {
            return false;
        }

        return $businessUser->branches()
            ->whereIn('business_branches.id', $accessibleBranchIds)
            ->exists();
    }
}
