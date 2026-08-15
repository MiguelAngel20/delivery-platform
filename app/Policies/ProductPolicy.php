<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Product;
use App\Models\User;
use App\Support\CatalogAccess;

class ProductPolicy
{
    public function __construct(
        private readonly CatalogAccess $catalogAccess,
    ) {}

    public function viewAny(User $user): bool
    {
        return $user->canAccessAdmin() || $user->canAccessBusiness();
    }

    public function view(User $user, Product $product): bool
    {
        $product->loadMissing('branch.business');

        return $this->catalogAccess->canManageBranchCatalog($user, $product->branch)
            || $user->canAccessBranch($product->branch);
    }

    public function create(User $user): bool
    {
        return $user->canAccessAdmin() || $user->hasRole(UserRole::BusinessAdmin);
    }

    public function update(User $user, Product $product): bool
    {
        $product->loadMissing('branch.business');

        return $this->catalogAccess->canManageBranchCatalog($user, $product->branch);
    }

    public function delete(User $user, Product $product): bool
    {
        return $this->update($user, $product);
    }
}
