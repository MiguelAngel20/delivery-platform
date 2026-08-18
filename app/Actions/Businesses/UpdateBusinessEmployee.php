<?php

namespace App\Actions\Businesses;

use App\Enums\BusinessUserRole;
use App\Enums\BusinessUserStatus;
use App\Enums\UserRole;
use App\Models\BusinessBranch;
use App\Models\BusinessUser;
use App\Models\User;
use App\Services\BusinessLimitService;
use App\Support\BusinessAccess;
use App\Support\BusinessMembershipBranchRules;
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
    public function handle(BusinessUser $membership, array $data, ?User $actor = null): BusinessUser
    {
        $role = $data['role'] instanceof BusinessUserRole
            ? $data['role']
            : BusinessUserRole::from((string) $data['role']);
        $status = $data['status'] instanceof BusinessUserStatus
            ? $data['status']
            : BusinessUserStatus::from((string) $data['status']);
        $branchIds = BusinessMembershipBranchRules::normalizeBranchIds($data['branch_ids'] ?? []);

        BusinessMembershipBranchRules::assertValidForRole($role, $branchIds);

        $business = $membership->business;

        if ($business === null) {
            throw ValidationException::withMessages([
                'role' => 'La membresía no pertenece a una empresa válida.',
            ]);
        }

        if ($actor !== null) {
            BusinessMembershipBranchRules::assertActorCanAssignBranches($actor, $business, $branchIds);
        }

        $ownedBranchIds = $this->businessAccess->ownedBranchIds($business, $branchIds);

        if (count($ownedBranchIds) !== count($branchIds)) {
            throw ValidationException::withMessages([
                'branch_ids' => 'Una o más sucursales no pertenecen a tu empresa.',
            ]);
        }

        return DB::transaction(function () use ($membership, $data, $role, $status, $ownedBranchIds, $business): BusinessUser {
            if ($role === BusinessUserRole::BusinessAdmin && $status === BusinessUserStatus::Active) {
                $this->limitService->assertCanAddBusinessAdmin($business, $membership->id);

                /** @var BusinessBranch $branch */
                $branch = $business->branches()->whereKey($ownedBranchIds[0])->lockForUpdate()->firstOrFail();
                $this->limitService->assertCanAssignAdminToBranch($branch, $membership->id);
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

            $membership->branches()->sync($ownedBranchIds);

            return $membership->fresh(['user', 'branches']);
        });
    }
}
