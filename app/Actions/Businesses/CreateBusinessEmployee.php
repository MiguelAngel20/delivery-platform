<?php

namespace App\Actions\Businesses;

use App\Enums\BusinessUserRole;
use App\Enums\BusinessUserStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Business;
use App\Models\BusinessBranch;
use App\Models\BusinessUser;
use App\Models\User;
use App\Services\BusinessLimitService;
use App\Support\BusinessAccess;
use App\Support\BusinessMembershipBranchRules;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateBusinessEmployee
{
    public function __construct(
        private readonly BusinessAccess $businessAccess,
        private readonly BusinessLimitService $limitService,
    ) {}

    /**
     * @param  array{
     *     first_name: string,
     *     last_name: string,
     *     email: string,
     *     phone: string,
     *     role: BusinessUserRole|string,
     *     status: BusinessUserStatus|string,
     *     branch_ids?: list<int|string>
     * }  $data
     */
    public function handle(Business $business, array $data, ?User $actor = null): BusinessUser
    {
        $role = $data['role'] instanceof BusinessUserRole
            ? $data['role']
            : BusinessUserRole::from((string) $data['role']);
        $status = $data['status'] instanceof BusinessUserStatus
            ? $data['status']
            : BusinessUserStatus::from((string) $data['status']);
        $branchIds = BusinessMembershipBranchRules::normalizeBranchIds($data['branch_ids'] ?? []);

        BusinessMembershipBranchRules::assertValidForRole($role, $branchIds);

        if ($actor !== null) {
            BusinessMembershipBranchRules::assertActorCanAssignBranches($actor, $business, $branchIds);
        }

        $ownedBranchIds = $this->businessAccess->ownedBranchIds($business, $branchIds);

        if (count($ownedBranchIds) !== count($branchIds)) {
            throw ValidationException::withMessages([
                'branch_ids' => 'Una o más sucursales no pertenecen a tu empresa.',
            ]);
        }

        return DB::transaction(function () use ($business, $data, $role, $status, $ownedBranchIds): BusinessUser {
            if ($role === BusinessUserRole::BusinessAdmin && $status === BusinessUserStatus::Active) {
                $this->limitService->assertCanAddBusinessAdmin($business);

                /** @var BusinessBranch $branch */
                $branch = $business->branches()->whereKey($ownedBranchIds[0])->lockForUpdate()->firstOrFail();
                $this->limitService->assertCanAssignAdminToBranch($branch);
            }

            if ($role === BusinessUserRole::BusinessEmployee && $status === BusinessUserStatus::Active) {
                $this->limitService->assertCanAssignEmployeeToBranches($business, $ownedBranchIds);
            }

            $user = $this->resolveUser($data, $role);

            BusinessMembershipBranchRules::assertUserAvailableForMembership($user);

            $alreadyMember = BusinessUser::query()
                ->where('business_id', $business->id)
                ->where('user_id', $user->id)
                ->exists();

            if ($alreadyMember) {
                throw ValidationException::withMessages([
                    'email' => 'Este usuario ya pertenece a la empresa.',
                ]);
            }

            /** @var BusinessUser $membership */
            $membership = BusinessUser::query()->create([
                'business_id' => $business->id,
                'user_id' => $user->id,
                'role' => $role,
                'status' => $status,
            ]);

            $membership->branches()->sync($ownedBranchIds);

            return $membership->load(['user', 'branches']);
        });
    }

    /**
     * @param  array{first_name: string, last_name: string, email: string, phone: string}  $data
     */
    private function resolveUser(array $data, BusinessUserRole $role): User
    {
        $existing = User::query()
            ->where(function ($query) use ($data): void {
                $query->where('email', $data['email'])
                    ->orWhere('phone', $data['phone']);
            })
            ->first();

        if ($existing !== null) {
            if (! $existing->hasRole(...UserRole::businessRoles())) {
                throw ValidationException::withMessages([
                    'email' => 'Ya existe un usuario con ese correo o teléfono que no puede asociarse como empleado.',
                ]);
            }

            if (
                ($existing->email === $data['email'] && $existing->phone !== $data['phone'] && User::query()->where('phone', $data['phone'])->where('id', '!=', $existing->id)->exists())
                || ($existing->phone === $data['phone'] && $existing->email !== $data['email'] && User::query()->where('email', $data['email'])->where('id', '!=', $existing->id)->exists())
            ) {
                throw ValidationException::withMessages([
                    'email' => 'El correo y el teléfono corresponden a usuarios distintos.',
                ]);
            }

            if ($existing->email !== $data['email'] && User::query()->where('email', $data['email'])->where('id', '!=', $existing->id)->exists()) {
                throw ValidationException::withMessages([
                    'email' => 'El correo ya está en uso por otro usuario.',
                ]);
            }

            if ($existing->phone !== $data['phone'] && User::query()->where('phone', $data['phone'])->where('id', '!=', $existing->id)->exists()) {
                throw ValidationException::withMessages([
                    'phone' => 'El teléfono ya está en uso por otro usuario.',
                ]);
            }

            $existing->fill([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'status' => UserStatus::Active,
            ]);

            if ($role === BusinessUserRole::BusinessAdmin) {
                $existing->role = UserRole::BusinessAdmin;
            } elseif ($existing->role !== UserRole::BusinessAdmin) {
                $existing->role = UserRole::BusinessEmployee;
            }

            $existing->save();

            return $existing;
        }

        return User::query()->create([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'password' => $this->temporaryPassword(),
            'must_change_password' => true,
            'role' => $role === BusinessUserRole::BusinessAdmin
                ? UserRole::BusinessAdmin
                : UserRole::BusinessEmployee,
            'status' => UserStatus::Active,
            'email_verified_at' => now(),
        ]);
    }

    private function temporaryPassword(): string
    {
        return (string) config('business.users.temporary_password', '12344321');
    }
}
