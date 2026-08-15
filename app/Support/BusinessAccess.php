<?php

namespace App\Support;

use App\Enums\BusinessUserRole;
use App\Enums\BusinessUserStatus;
use App\Models\Business;
use App\Models\BusinessBranch;
use App\Models\BusinessUser;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

final class BusinessAccess
{
    public function activeMembership(User $user, ?Business $business = null): ?BusinessUser
    {
        $query = $user->businessMemberships()
            ->where('status', BusinessUserStatus::Active)
            ->with(['business', 'branches']);

        if ($business !== null) {
            $query->where('business_id', $business->id);
        }

        return $query->first();
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

        if ($membership->role === BusinessUserRole::BusinessAdmin) {
            return $membership->business->branches()
                ->orderBy('name')
                ->get();
        }

        return $membership->branches()
            ->orderBy('name')
            ->get();
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
