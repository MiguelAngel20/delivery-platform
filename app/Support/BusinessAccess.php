<?php

namespace App\Support;

use App\Models\Business;
use App\Models\BusinessBranch;
use App\Models\BusinessUser;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

final class BusinessAccess
{
    public function activeMembership(User $user, ?Business $business = null): ?BusinessUser
    {
        $membership = $user->activeBusinessMembership($business);

        if ($membership === null) {
            return null;
        }

        return $membership->loadMissing(['business', 'branches']);
    }

    /**
     * @return Collection<int, BusinessBranch>
     */
    public function accessibleBranches(User $user, ?Business $business = null): Collection
    {
        $membership = $this->activeMembership($user, $business);

        if ($membership === null || $membership->business === null) {
            return new Collection;
        }

        return $membership->branches()
            ->orderBy('name')
            ->get();
    }

    /**
     * @return list<int>
     */
    public function accessibleBranchIds(User $user, ?Business $business = null): array
    {
        return $this->accessibleBranches($user, $business)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();
    }

    public function canAccessBranch(User $user, BusinessBranch $branch): bool
    {
        return $user->canAccessBranch($branch);
    }

    /**
     * @param  list<int>  $branchIds
     * @return list<int>
     */
    public function ownedBranchIds(Business $business, array $branchIds): array
    {
        if ($branchIds === []) {
            return [];
        }

        return $business->branches()
            ->whereIn('id', $branchIds)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }
}
