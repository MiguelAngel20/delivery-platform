<?php

use App\Enums\DriverScope;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Driver;
use App\Models\User;
use App\Notifications\Auth\DriverEmailVerification;
use Illuminate\Support\Facades\Notification;

test('admin can create a platform driver and receives verification email', function () {
    Notification::fake();

    $admin = User::factory()->systemAdmin()->create();

    $this->actingAs($admin)
        ->post(route('admin.drivers.store'), [
            'first_name' => 'Luis',
            'last_name' => 'Pérez',
            'email' => 'luis.repartidor@example.com',
            'phone' => '+529611111111',
        ])
        ->assertRedirect();

    $user = User::query()->where('email', 'luis.repartidor@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->role)->toBe(UserRole::Driver)
        ->and($user->email_verified_at)->toBeNull()
        ->and($user->must_change_password)->toBeTrue()
        ->and($user->driver)->not->toBeNull()
        ->and($user->driver->driver_scope)->toBe(DriverScope::Platform);

    Notification::assertSentTo($user, DriverEmailVerification::class);
});

test('admin can update a platform driver', function () {
    $admin = User::factory()->systemAdmin()->create();
    $user = User::factory()->driver()->create([
        'first_name' => 'Ana',
        'last_name' => 'Ruiz',
        'email' => 'ana.ruiz@example.com',
        'phone' => '+529622222222',
        'email_verified_at' => now(),
    ]);
    $driver = Driver::factory()->forUser($user)->approved($admin)->create();

    $this->actingAs($admin)
        ->put(route('admin.drivers.update', $driver), [
            'first_name' => 'Ana María',
            'last_name' => 'Ruiz',
            'email' => 'ana.ruiz@example.com',
            'phone' => '+529633333333',
        ])
        ->assertRedirect();

    expect($user->fresh()->first_name)->toBe('Ana María')
        ->and($user->fresh()->phone)->toBe('+529633333333')
        ->and($user->fresh()->email_verified_at)->not->toBeNull();
});

test('changing driver email resets verification and resends mail', function () {
    Notification::fake();

    $admin = User::factory()->systemAdmin()->create();
    $user = User::factory()->driver()->create([
        'email' => 'viejo@example.com',
        'phone' => '+529644444444',
        'email_verified_at' => now(),
    ]);
    $driver = Driver::factory()->forUser($user)->approved($admin)->create();

    $this->actingAs($admin)
        ->put(route('admin.drivers.update', $driver), [
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => 'nuevo@example.com',
            'phone' => $user->phone,
        ])
        ->assertRedirect();

    expect($user->fresh()->email)->toBe('nuevo@example.com')
        ->and($user->fresh()->email_verified_at)->toBeNull();

    Notification::assertSentTo($user->fresh(), DriverEmailVerification::class);
});

test('admin can delete a platform driver', function () {
    $admin = User::factory()->systemAdmin()->create();
    $user = User::factory()->driver()->create([
        'email' => 'borrar@example.com',
        'phone' => '+529655555555',
    ]);
    $driver = Driver::factory()->forUser($user)->approved($admin)->create();

    $this->actingAs($admin)
        ->delete(route('admin.drivers.destroy', $driver))
        ->assertRedirect();

    expect(Driver::query()->whereKey($driver->id)->exists())->toBeFalse()
        ->and(User::query()->whereKey($user->id)->exists())->toBeFalse()
        ->and(User::withTrashed()->whereKey($user->id)->first()?->status)->toBe(UserStatus::Inactive);
});

test('admin drivers index includes create form fields', function () {
    $admin = User::factory()->systemAdmin()->create();
    $user = User::factory()->driver()->create();
    Driver::factory()->forUser($user)->approved($admin)->create();

    $this->actingAs($admin)
        ->get(route('admin.drivers.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/drivers/index')
            ->has('drivers.data.0.first_name')
            ->has('drivers.data.0.email_verified'));
});
