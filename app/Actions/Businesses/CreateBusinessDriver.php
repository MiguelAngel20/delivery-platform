<?php

namespace App\Actions\Businesses;

use App\Enums\DriverApprovalStatus;
use App\Enums\DriverAvailabilityStatus;
use App\Enums\DriverPaymentModel;
use App\Enums\DriverScope;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Business;
use App\Models\Driver;
use App\Models\User;
use App\Support\BusinessAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateBusinessDriver
{
    public function __construct(
        private readonly BusinessAccess $businessAccess,
    ) {}

    /**
     * @param  array{
     *     first_name: string,
     *     last_name: string,
     *     email: string,
     *     phone: string,
     *     branch_ids?: list<int|string>
     * }  $data
     */
    public function handle(Business $business, array $data, ?User $actor = null): Driver
    {
        $branchIds = collect($data['branch_ids'] ?? [])
            ->map(fn (int|string $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($branchIds === []) {
            throw ValidationException::withMessages([
                'branch_ids' => 'Asigna al menos una sucursal.',
            ]);
        }

        $ownedBranchIds = $this->businessAccess->ownedBranchIds($business, $branchIds);

        if (count($ownedBranchIds) !== count($branchIds)) {
            throw ValidationException::withMessages([
                'branch_ids' => 'Una o más sucursales no pertenecen a esta empresa.',
            ]);
        }

        return DB::transaction(function () use ($business, $data, $ownedBranchIds, $actor): Driver {
            $user = $this->resolveUser($data);
            $driver = $this->resolveDriver($user, $actor);

            $alreadyLinked = $driver->businesses()->whereKey($business->id)->exists();

            if ($alreadyLinked) {
                throw ValidationException::withMessages([
                    'email' => 'Este repartidor ya pertenece a la empresa.',
                ]);
            }

            $driver->businesses()->attach($business->id);
            $driver->branches()->syncWithoutDetaching($ownedBranchIds);

            return $driver->load(['user', 'branches']);
        });
    }

    /**
     * @param  array{first_name: string, last_name: string, email: string, phone: string}  $data
     */
    private function resolveUser(array $data): User
    {
        $existing = User::query()
            ->where(function ($query) use ($data): void {
                $query->where('email', $data['email'])
                    ->orWhere('phone', $data['phone']);
            })
            ->first();

        if ($existing !== null) {
            if ($existing->role !== UserRole::Driver) {
                throw ValidationException::withMessages([
                    'email' => 'Ya existe un usuario con ese correo o teléfono que no puede asociarse como repartidor.',
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

            $existing->fill([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'status' => UserStatus::Active,
            ]);
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
            'role' => UserRole::Driver,
            'status' => UserStatus::Active,
            'email_verified_at' => now(),
        ]);
    }

    private function resolveDriver(User $user, ?User $actor): Driver
    {
        $driver = Driver::query()->withTrashed()->where('user_id', $user->id)->first();

        if ($driver !== null) {
            if ($driver->trashed()) {
                $driver->restore();
            }

            if ($driver->driver_scope === DriverScope::Platform) {
                throw ValidationException::withMessages([
                    'email' => 'Este usuario ya es repartidor de plataforma y no puede asignarse como propio de una empresa.',
                ]);
            }

            $driver->fill([
                'approval_status' => DriverApprovalStatus::Approved,
                'driver_scope' => DriverScope::BusinessOnly,
                'payment_model' => DriverPaymentModel::BusinessRate,
                'approved_by_user_id' => $driver->approved_by_user_id ?? $actor?->id,
                'approved_at' => $driver->approved_at ?? now(),
            ]);
            $driver->save();

            return $driver;
        }

        return Driver::query()->create([
            'user_id' => $user->id,
            'approval_status' => DriverApprovalStatus::Approved,
            'availability_status' => DriverAvailabilityStatus::Offline,
            'driver_scope' => DriverScope::BusinessOnly,
            'payment_model' => DriverPaymentModel::BusinessRate,
            'approved_by_user_id' => $actor?->id,
            'approved_at' => now(),
        ]);
    }

    private function temporaryPassword(): string
    {
        return (string) config('business.users.temporary_password', '12344321');
    }
}
