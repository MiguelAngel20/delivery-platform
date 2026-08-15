<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\ProductCategory;
use App\Models\User;
use App\Support\CatalogAccess;

class ProductCategoryPolicy
{
    public function __construct(
        private readonly CatalogAccess $catalogAccess,
    ) {}

    public function viewAny(User $user): bool
    {
        return $user->canAccessAdmin() || $user->canAccessBusiness();
    }

    public function view(User $user, ProductCategory $category): bool
    {
        $category->loadMissing('branch.business');

        return $this->catalogAccess->canManageBranchCatalog($user, $category->branch)
            || $user->canAccessBranch($category->branch);
    }

    public function create(User $user): bool
    {
        return $user->canAccessAdmin() || $user->hasRole(UserRole::BusinessAdmin);
    }

    public function update(User $user, ProductCategory $category): bool
    {
        $category->loadMissing('branch.business');

        return $this->catalogAccess->canManageBranchCatalog($user, $category->branch);
    }

    public function delete(User $user, ProductCategory $category): bool
    {
        return $this->update($user, $category);
    }
}
