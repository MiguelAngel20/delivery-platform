<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Seeder;

class SystemAdminSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'm.angel.desarrolladorweb@gmail.com'],
            [
                'first_name' => 'Miguel Angel',
                'last_name' => 'Rodriguez',
                'name' => 'Miguel Angel Rodriguez',
                'phone' => '9631567144',
                'password' => 'Alacranes20#',
                'must_change_password' => false,
                'role' => UserRole::SystemAdmin,
                'status' => UserStatus::Active,
                'email_verified_at' => now(),
                'phone_verified_at' => now(),
            ],
        );
    }
}
