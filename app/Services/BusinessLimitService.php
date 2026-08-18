<?php

namespace App\Services;

use App\Enums\BusinessUserRole;
use App\Enums\BusinessUserStatus;
use App\Models\Business;
use App\Models\BusinessBranch;
use App\Models\BusinessLimit;
use App\Models\BusinessUser;
use Illuminate\Validation\ValidationException;

class BusinessLimitService
{
    public function ensureLimits(Business $business): BusinessLimit
    {
        if ($business->relationLoaded('limits') && $business->limits !== null) {
            return $business->limits;
        }

        $existing = $business->limits()->first();

        if ($existing !== null) {
            return $existing;
        }

        return $business->limits()->create([
            'max_branches' => (int) config('business.defaults.max_branches'),
            'max_business_admins' => (int) config('business.defaults.max_business_admins'),
            'max_employees_per_branch' => (int) config('business.defaults.max_employees_per_branch'),
        ]);
    }

    public function lockForUpdate(Business $business): BusinessLimit
    {
        $this->ensureLimits($business);

        /** @var BusinessLimit $limits */
        $limits = $business->limits()->lockForUpdate()->firstOrFail();

        return $limits;
    }

    public function branchCount(Business $business): int
    {
        return $business->branches()->count();
    }

    public function activeBusinessAdminCount(Business $business): int
    {
        return $business->memberships()
            ->where('role', BusinessUserRole::BusinessAdmin)
            ->where('status', BusinessUserStatus::Active)
            ->count();
    }

    public function activeEmployeesOnBranch(BusinessBranch $branch, ?int $exceptMembershipId = null): int
    {
        return BusinessUser::query()
            ->where('business_id', $branch->business_id)
            ->where('role', BusinessUserRole::BusinessEmployee)
            ->where('status', BusinessUserStatus::Active)
            ->whereHas('branches', fn ($query) => $query->where('business_branches.id', $branch->id))
            ->when(
                $exceptMembershipId !== null,
                fn ($query) => $query->where('id', '!=', $exceptMembershipId),
            )
            ->count();
    }

    public function activeBusinessAdminOnBranch(BusinessBranch $branch, ?int $exceptMembershipId = null): int
    {
        return BusinessUser::query()
            ->where('business_id', $branch->business_id)
            ->where('role', BusinessUserRole::BusinessAdmin)
            ->where('status', BusinessUserStatus::Active)
            ->whereHas('branches', fn ($query) => $query->where('business_branches.id', $branch->id))
            ->when(
                $exceptMembershipId !== null,
                fn ($query) => $query->where('id', '!=', $exceptMembershipId),
            )
            ->count();
    }

    public function canCreateBranch(Business $business): bool
    {
        $limits = $this->ensureLimits($business);

        return $this->branchCount($business) < $limits->max_branches;
    }

    public function canAddBusinessAdmin(Business $business, ?int $exceptMembershipId = null): bool
    {
        $limits = $this->ensureLimits($business);

        $count = $business->memberships()
            ->where('role', BusinessUserRole::BusinessAdmin)
            ->where('status', BusinessUserStatus::Active)
            ->when(
                $exceptMembershipId !== null,
                fn ($query) => $query->where('id', '!=', $exceptMembershipId),
            )
            ->count();

        return $count < $limits->max_business_admins;
    }

    public function canAssignEmployeeToBranch(
        BusinessBranch $branch,
        ?int $exceptMembershipId = null,
    ): bool {
        $business = $branch->relationLoaded('business') && $branch->business !== null
            ? $branch->business
            : Business::query()->findOrFail($branch->business_id);

        $limits = $this->ensureLimits($business);

        return $this->activeEmployeesOnBranch($branch, $exceptMembershipId)
            < $limits->max_employees_per_branch;
    }

    /**
     * @param  list<int>  $branchIds
     */
    public function assertCanAssignEmployeeToBranches(
        Business $business,
        array $branchIds,
        ?int $exceptMembershipId = null,
    ): void {
        $limits = $this->lockForUpdate($business);

        foreach (array_unique($branchIds) as $branchId) {
            /** @var BusinessBranch|null $branch */
            $branch = $business->branches()->whereKey($branchId)->lockForUpdate()->first();

            if ($branch === null) {
                throw ValidationException::withMessages([
                    'branch_ids' => 'Una o más sucursales no pertenecen a tu empresa.',
                ]);
            }

            $used = $this->activeEmployeesOnBranch($branch, $exceptMembershipId);

            if ($used >= $limits->max_employees_per_branch) {
                throw ValidationException::withMessages([
                    'branch_ids' => 'Has alcanzado el límite de empleados permitido para esta sucursal.',
                ]);
            }
        }
    }

    public function assertCanAddBusinessAdmin(
        Business $business,
        ?int $exceptMembershipId = null,
    ): void {
        $this->lockForUpdate($business);

        if (! $this->canAddBusinessAdmin($business, $exceptMembershipId)) {
            throw ValidationException::withMessages([
                'role' => 'Has alcanzado el límite de administradores permitido para esta empresa.',
            ]);
        }
    }

    public function assertCanAssignAdminToBranch(
        BusinessBranch $branch,
        ?int $exceptMembershipId = null,
    ): void {
        if ($this->activeBusinessAdminOnBranch($branch, $exceptMembershipId) >= 1) {
            throw ValidationException::withMessages([
                'branch_ids' => 'Esta sucursal ya tiene un administrador activo.',
            ]);
        }
    }

    public function assertCanCreateBranch(Business $business): void
    {
        $limits = $this->lockForUpdate($business);

        if ($this->branchCount($business) >= $limits->max_branches) {
            throw ValidationException::withMessages([
                'name' => 'La empresa alcanzó el límite de sucursales contratado. Amplía el límite antes de crear otra sucursal.',
            ]);
        }
    }

    /**
     * @return array{
     *     max_branches: int,
     *     max_business_admins: int,
     *     max_employees_per_branch: int,
     *     branches_used: int,
     *     business_admins_used: int,
     *     can_create_branch: bool,
     *     can_add_business_admin: bool,
     *     branch_employee_usage: list<array{branch_id: int, branch_name: string, used: int, max: int, remaining: int}>
     * }
     */
    public function summary(Business $business): array
    {
        $limits = $this->ensureLimits($business);
        $branchesUsed = $this->branchCount($business);
        $adminsUsed = $this->activeBusinessAdminCount($business);

        $branchUsage = $business->branches()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(function (BusinessBranch $branch) use ($limits): array {
                $used = $this->activeEmployeesOnBranch($branch);

                return [
                    'branch_id' => $branch->id,
                    'branch_name' => $branch->name,
                    'used' => $used,
                    'max' => $limits->max_employees_per_branch,
                    'remaining' => max(0, $limits->max_employees_per_branch - $used),
                ];
            })
            ->values()
            ->all();

        return [
            'max_branches' => $limits->max_branches,
            'max_business_admins' => $limits->max_business_admins,
            'max_employees_per_branch' => $limits->max_employees_per_branch,
            'branches_used' => $branchesUsed,
            'business_admins_used' => $adminsUsed,
            'can_create_branch' => $branchesUsed < $limits->max_branches,
            'can_add_business_admin' => $adminsUsed < $limits->max_business_admins,
            'branch_employee_usage' => $branchUsage,
        ];
    }
}
