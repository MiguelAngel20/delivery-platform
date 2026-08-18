<?php

namespace App\Support;

use App\Enums\BusinessUserRole;
use App\Enums\UserRole;
use App\Models\Business;
use App\Models\User;
use Illuminate\Validation\ValidationException;

final class BusinessMembershipBranchRules
{
    /**
     * @param  list<int|string>  $branchIds
     * @return list<int>
     */
    public static function normalizeBranchIds(array $branchIds): array
    {
        return array_values(array_unique(array_map('intval', $branchIds)));
    }

    /**
     * @param  list<int|string>  $branchIds
     */
    public static function assertValidForRole(BusinessUserRole $role, array $branchIds): void
    {
        $normalized = self::normalizeBranchIds($branchIds);

        if ($role === BusinessUserRole::BusinessAdmin) {
            if (count($normalized) !== 1) {
                throw ValidationException::withMessages([
                    'branch_ids' => 'El administrador debe tener exactamente una sucursal asignada.',
                ]);
            }

            return;
        }

        if ($normalized === []) {
            throw ValidationException::withMessages([
                'branch_ids' => 'El empleado debe tener al menos una sucursal asignada.',
            ]);
        }
    }

    /**
     * @param  list<int>  $branchIds
     */
    public static function assertActorCanAssignBranches(User $actor, Business $business, array $branchIds): void
    {
        if ($actor->hasRole(UserRole::SystemAdmin)) {
            return;
        }

        $accessible = app(BusinessAccess::class)->accessibleBranchIds($actor, $business);
        $invalid = array_diff($branchIds, $accessible);

        if ($invalid !== []) {
            throw ValidationException::withMessages([
                'branch_ids' => 'No puedes asignar sucursales fuera de tu alcance.',
            ]);
        }
    }
}
