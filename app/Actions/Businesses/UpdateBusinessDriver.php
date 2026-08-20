<?php

namespace App\Actions\Businesses;

use App\Models\Business;
use App\Models\Driver;
use App\Models\User;
use App\Support\BusinessAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateBusinessDriver
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
    public function handle(Business $business, Driver $driver, array $data, ?User $actor = null): Driver
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

        if ($actor !== null) {
            $accessibleBranchIds = $this->businessAccess->accessibleBranchIds($actor, $business);
            $invalidBranchIds = array_diff($branchIds, $accessibleBranchIds);

            if ($invalidBranchIds !== []) {
                throw ValidationException::withMessages([
                    'branch_ids' => 'Una o más sucursales no están disponibles para tu cuenta.',
                ]);
            }
        }

        $ownedBranchIds = $this->businessAccess->ownedBranchIds($business, $branchIds);

        if (count($ownedBranchIds) !== count($branchIds)) {
            throw ValidationException::withMessages([
                'branch_ids' => 'Una o más sucursales no pertenecen a esta empresa.',
            ]);
        }

        if ($actor !== null) {
            $accessibleBranchIds = $this->businessAccess->accessibleBranchIds($actor, $business);
            $preservedBranchIds = $driver->branches()
                ->where('business_id', $business->id)
                ->whereNotIn('business_branches.id', $accessibleBranchIds)
                ->pluck('business_branches.id')
                ->map(fn ($id): int => (int) $id)
                ->all();

            $ownedBranchIds = array_values(array_unique([
                ...$preservedBranchIds,
                ...$ownedBranchIds,
            ]));
        }

        return DB::transaction(function () use ($business, $driver, $data, $ownedBranchIds): Driver {
            $user = $driver->user;

            if ($user === null) {
                throw ValidationException::withMessages([
                    'email' => 'El repartidor no tiene una cuenta válida.',
                ]);
            }

            $emailTaken = User::query()
                ->where('email', $data['email'])
                ->where('id', '!=', $user->id)
                ->exists();

            if ($emailTaken) {
                throw ValidationException::withMessages([
                    'email' => 'El correo ya está en uso.',
                ]);
            }

            $phoneTaken = User::query()
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
                'email' => $data['email'],
                'phone' => $data['phone'],
            ]);
            $user->save();

            $otherBusinessBranchIds = $driver->branches()
                ->where('business_id', '!=', $business->id)
                ->pluck('business_branches.id')
                ->map(fn ($id): int => (int) $id)
                ->all();

            $driver->branches()->sync(array_values(array_unique([
                ...$otherBusinessBranchIds,
                ...$ownedBranchIds,
            ])));

            return $driver->fresh(['user', 'branches']);
        });
    }
}
