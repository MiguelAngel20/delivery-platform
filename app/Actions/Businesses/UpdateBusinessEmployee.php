<?php

namespace App\Actions\Businesses;

use App\Enums\BusinessUserRole;
use App\Enums\BusinessUserStatus;
use App\Enums\UserRole;
use App\Models\BusinessUser;
use App\Services\BusinessLimitService;
use App\Support\BusinessAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateBusinessEmployee
{
    public function __construct(
        private readonly BusinessAccess $businessAccess,
        private readonly BusinessLimitService $limitService,
    ) {}

    /**
     * @param  array{
     *     first_name: string,
     *     last_name: string,
     *     email?: string,
     *     phone: string,
     *     role: BusinessUserRole|string,
     *     status: BusinessUserStatus|string,
     *     branch_ids?: list<int|string>
     * }  $data
     */
    public function handle(BusinessUser $membership, array $data): BusinessUser
    {
        $role = $data['role'] instanceof BusinessUserRole
            ? $data['role']
            : BusinessUserRole::from((string) $data['role']);
        $status = $data['status'] instanceof BusinessUserStatus
            ? $data['status']
            : BusinessUserStatus::from((string) $data['status']);
        $branchIds = array_map('intval', $data['branch_ids'] ?? []);

        $ownedBranchIds = $this->businessAccess->ownedBranchIds($membership->business, $branchIds);

        if (count($ownedBranchIds) !== count(array_unique($branchIds))) {
            throw ValidationException::withMessages([
                'branch_ids' => 'Una o más sucursales no pertenecen a tu empresa.',
            ]);
        }

        if ($role === BusinessUserRole::BusinessEmployee && $ownedBranchIds === []) {
            throw ValidationException::withMessages([
                'branch_ids' => 'El empleado debe tener al menos una sucursal asignada.',
            ]);
        }

        return DB::transaction(function () use ($membership, $data, $role, $status, $ownedBranchIds): BusinessUser {
            $business = $membership->business;

            if ($role === BusinessUserRole::BusinessAdmin && $status === BusinessUserStatus::Active) {
                $this->limitService->assertCanAddBusinessAdmin($business, $membership->id);
            }

            if ($role === BusinessUserRole::BusinessEmployee && $status === BusinessUserStatus::Active) {
                $this->limitService->assertCanAssignEmployeeToBranches(
                    $business,
                    $ownedBranchIds,
                    $membership->id,
                );
            }

            $user = $membership->user;

            $email = $data['email'] ?? $user->email;

            if ($email !== $user->email) {
                $emailTaken = $user->newQuery()
                    ->where('email', $email)
                    ->where('id', '!=', $user->id)
                    ->exists();

                if ($emailTaken) {
                    throw ValidationException::withMessages([
                        'email' => 'El correo ya está en uso.',
                    ]);
                }
            }

            $phoneTaken = $user->newQuery()
                ->where('phone', $data['phone'])
                ->where('id', '!=', $user->id)
                ->exists();

            if ($phoneTaken) {
                throw ValidationException::withMessages([
                    'phone' => 'El teléfono ya está en uso.',
                ]);
            }

            $user->fill([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $email,
                'phone' => $data['phone'],
            ]);

            if ($role === BusinessUserRole::BusinessAdmin) {
                $user->role = UserRole::BusinessAdmin;
            } elseif ($user->role !== UserRole::BusinessAdmin) {
                $user->role = UserRole::BusinessEmployee;
            }

            $user->save();

            $membership->update([
                'role' => $role,
                'status' => $status,
            ]);

            if ($role === BusinessUserRole::BusinessEmployee) {
                $membership->branches()->sync($ownedBranchIds);
            } else {
                $membership->branches()->sync([]);
            }

            return $membership->fresh(['user', 'branches']);
        });
    }
}
