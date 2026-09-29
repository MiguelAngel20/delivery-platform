<?php

namespace App\Actions\Admin;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use App\Support\AdminAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveAdminUser
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): User
    {
        return DB::transaction(function () use ($data): User {
            $user = User::query()->create([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'password' => $data['password'],
                'role' => UserRole::SystemAdmin,
                'status' => UserStatus::Active,
                'email_verified_at' => now(),
                'phone_verified_at' => now(),
                'must_change_password' => false,
            ]);

            $user->forceFill(['is_platform_owner' => false])->save();

            AdminAccess::sync($user, $data['permissions']);

            return $user;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $user, array $data): User
    {
        if (! $user->hasRole(UserRole::SystemAdmin) || $user->isPlatformOwner()) {
            throw ValidationException::withMessages([
                'user' => 'Esta cuenta no se puede editar desde aquí.',
            ]);
        }

        return DB::transaction(function () use ($user, $data): User {
            $user->forceFill([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
            ]);

            if (filled($data['password'] ?? null)) {
                $user->password = $data['password'];
            }

            $user->save();

            AdminAccess::sync($user, $data['permissions']);

            return $user;
        });
    }
}
