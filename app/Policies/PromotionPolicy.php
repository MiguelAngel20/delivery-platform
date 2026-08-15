<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Promotion;
use App\Models\User;
use App\Support\CatalogAccess;

class PromotionPolicy
{
    public function __construct(
        private readonly CatalogAccess $catalogAccess,
    ) {}

    public function viewAny(User $user): bool
    {
        return $user->canAccessAdmin() || $user->canAccessBusiness();
    }

    public function view(User $user, Promotion $promotion): bool
    {
        $promotion->loadMissing('branch.business');

        return $this->catalogAccess->canManageBranchCatalog($user, $promotion->branch)
            || $user->canAccessBranch($promotion->branch);
    }

    public function create(User $user): bool
    {
        return $user->canAccessAdmin() || $user->hasRole(UserRole::BusinessAdmin);
    }

    public function update(User $user, Promotion $promotion): bool
    {
        $promotion->loadMissing('branch.business');

        return $this->catalogAccess->canManageBranchCatalog($user, $promotion->branch);
    }

    public function delete(User $user, Promotion $promotion): bool
    {
        return $this->update($user, $promotion);
    }
}
