<?php

use App\Enums\AdminAbility;
use App\Enums\AdminSection;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Customer;
use App\Models\User;
use App\Support\AdminAccess;

test('a missing platform owner flag is treated as false', function () {
    $user = User::factory()->systemAdmin()->create();
    $user->is_platform_owner = null;

    expect($user->isPlatformOwner())->toBeFalse();
});

test('the platform owner keeps full admin access', function () {
    $owner = User::factory()->systemAdmin()->create();

    expect($owner->isPlatformOwner())->toBeTrue();

    $this->actingAs($owner)
        ->get(route('admin.businesses.index'))
        ->assertOk();

    $this->actingAs($owner)
        ->get(route('admin.settings.admins.index'))
        ->assertOk();
});

test('a limited admin only opens the sections that were granted', function () {
    $staff = User::factory()->create([
        'role' => UserRole::SystemAdmin,
        'is_platform_owner' => false,
        'email_verified_at' => now(),
    ]);

    AdminAccess::sync($staff, AdminAccess::permissionInput([
        AdminSection::Customers->value => ['view' => true],
        AdminSection::Orders->value => ['view' => true, 'update' => true],
    ]));

    $this->actingAs($staff)
        ->get(route('admin.home'))
        ->assertOk();

    $this->actingAs($staff)
        ->get(route('admin.customers.index'))
        ->assertOk();

    $this->actingAs($staff)
        ->get(route('admin.businesses.index'))
        ->assertForbidden();

    $this->actingAs($staff)
        ->get(route('admin.settings.admins.index'))
        ->assertForbidden();

    $this->actingAs($staff)
        ->get(route('admin.settings.activity-suspension.edit'))
        ->assertForbidden();

    $customer = Customer::factory()->create();

    $this->actingAs($staff)
        ->delete(route('admin.customers.destroy', $customer))
        ->assertForbidden();

    expect(Customer::query()->find($customer->id))->not->toBeNull();
});

test('the owner can create a limited admin and that account cannot grant access', function () {
    $owner = User::factory()->systemAdmin()->create();

    $this->actingAs($owner)
        ->post(route('admin.settings.admins.store'), [
            'first_name' => 'Ana',
            'last_name' => 'López',
            'email' => 'ana.admin@example.com',
            'phone' => '+529611110001',
            'password' => 'Secret1!a',
            'password_confirmation' => 'Secret1!a',
            'permissions' => AdminAccess::permissionInput([
                AdminSection::Drivers->value => [
                    'view' => true,
                    'create' => true,
                ],
            ]),
        ])
        ->assertRedirect(route('admin.settings.admins.index'));

    $staff = User::query()->where('email', 'ana.admin@example.com')->first();

    expect($staff)->not->toBeNull()
        ->and($staff->role)->toBe(UserRole::SystemAdmin)
        ->and($staff->isPlatformOwner())->toBeFalse()
        ->and($staff->email_verified_at)->not->toBeNull();

    expect(AdminAccess::allows($staff, AdminSection::Drivers, AdminAbility::Create))->toBeTrue()
        ->and(AdminAccess::allows($staff, AdminSection::Drivers, AdminAbility::Delete))->toBeFalse()
        ->and(AdminAccess::allows($staff, AdminSection::Finance, AdminAbility::View))->toBeFalse();

    $this->actingAs($staff)
        ->post(route('admin.settings.admins.store'), [
            'first_name' => 'Otro',
            'last_name' => 'Admin',
            'email' => 'otro.admin@example.com',
            'phone' => '+529611110002',
            'password' => 'Secret1!a',
            'password_confirmation' => 'Secret1!a',
            'permissions' => AdminAccess::permissionInput(),
        ])
        ->assertForbidden();
});

test('the owner cannot demote or deactivate their own owner account', function () {
    $owner = User::factory()->systemAdmin()->create();

    $this->actingAs($owner)
        ->get(route('admin.settings.admins.edit', $owner))
        ->assertForbidden();

    $this->actingAs($owner)
        ->post(route('admin.settings.admins.deactivate', $owner))
        ->assertForbidden();

    expect($owner->fresh()->status)->toBe(UserStatus::Active)
        ->and($owner->fresh()->isPlatformOwner())->toBeTrue();
});

test('the owner can deactivate a limited admin', function () {
    $owner = User::factory()->systemAdmin()->create();
    $staff = User::factory()->create([
        'role' => UserRole::SystemAdmin,
        'is_platform_owner' => false,
    ]);

    $this->actingAs($owner)
        ->post(route('admin.settings.admins.deactivate', $staff))
        ->assertRedirect();

    expect($staff->fresh()->status)->toBe(UserStatus::Inactive);

    $this->actingAs($staff->fresh())
        ->get(route('admin.home'))
        ->assertForbidden();
});
