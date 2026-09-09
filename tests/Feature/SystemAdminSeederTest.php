<?php

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Hash;

test('database seeder creates only the system admin user', function () {
    $this->seed(DatabaseSeeder::class);

    expect(User::query()->count())->toBe(1);

    $admin = User::query()->where('email', 'm.angel.desarrolladorweb@gmail.com')->first();

    expect($admin)->not->toBeNull()
        ->and($admin->name)->toBe('Miguel Angel Rodriguez')
        ->and($admin->first_name)->toBe('Miguel Angel')
        ->and($admin->last_name)->toBe('Rodriguez')
        ->and($admin->phone)->toBe('9631567144')
        ->and($admin->role)->toBe(UserRole::SystemAdmin)
        ->and($admin->status)->toBe(UserStatus::Active)
        ->and($admin->must_change_password)->toBeFalse()
        ->and(Hash::check('Alacranes20#', $admin->password))->toBeTrue();
});

test('system admin seeder is idempotent', function () {
    $this->seed(DatabaseSeeder::class);
    $this->seed(DatabaseSeeder::class);

    expect(User::query()->count())->toBe(1);
});
