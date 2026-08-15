<?php

namespace App\Http\Controllers\Web\Business\Concerns;

use App\Models\Business;
use App\Models\BusinessBranch;
use App\Models\User;
use App\Support\CatalogAccess;
use Illuminate\Http\Request;

trait ResolvesBusinessCatalog
{
    protected function currentBusiness(Request $request): Business
    {
        /** @var User $user */
        $user = $request->user();
        $membership = $user->activeBusinessMembership();

        abort_unless(
            $membership !== null && $membership->isAdmin() && $membership->business !== null,
            403,
        );

        return $membership->business;
    }

    protected function resolveBranch(Request $request, Business $business, int|string $branchId): BusinessBranch
    {
        $branch = BusinessBranch::query()
            ->where('business_id', $business->id)
            ->whereKey($branchId)
            ->firstOrFail();

        abort_unless(
            app(CatalogAccess::class)->canManageBranchCatalog($request->user(), $branch),
            403,
        );

        return $branch;
    }
}
