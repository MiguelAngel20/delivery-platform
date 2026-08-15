<?php

namespace App\Policies;

use App\Models\Business;
use App\Models\PricingRule;
use App\Models\User;
use App\Support\CatalogAccess;

class PricingRulePolicy
{
    public function __construct(
        private readonly CatalogAccess $catalogAccess,
    ) {}

    public function viewAny(User $user): bool
    {
        return $user->canAccessAdmin();
    }

    public function create(User $user, Business $business): bool
    {
        return $this->catalogAccess->canManagePricingRules($user, $business);
    }

    public function update(User $user, PricingRule $rule): bool
    {
        $rule->loadMissing('branch.business');

        return $rule->branch?->business !== null
            && $this->catalogAccess->canManagePricingRules($user, $rule->branch->business);
    }

    public function delete(User $user, PricingRule $rule): bool
    {
        return $this->update($user, $rule);
    }
}
