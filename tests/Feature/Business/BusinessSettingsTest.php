<?php

use App\Enums\BusinessDeliveryMode;
use App\Enums\BusinessUserRole;
use App\Enums\BusinessUserStatus;
use App\Models\Business;
use App\Models\BusinessBranch;
use App\Models\BusinessUser;
use App\Models\User;
use App\Support\BusinessHours;

function seedBusinessAdminForSettings(): array
{
    $admin = User::factory()->businessAdmin()->create();
    $business = Business::factory()->create([
        'delivery_mode' => BusinessDeliveryMode::OwnDrivers,
    ]);
    $branch = BusinessBranch::factory()->for($business)->create(['name' => 'Centro']);

    BusinessUser::query()->create([
        'business_id' => $business->id,
        'user_id' => $admin->id,
        'role' => BusinessUserRole::BusinessAdmin,
        'status' => BusinessUserStatus::Active,
    ])->branches()->sync([$branch->id]);

    return compact('admin', 'business', 'branch');
}

test('business admin can view settings hub', function () {
    ['admin' => $admin] = seedBusinessAdminForSettings();

    $this->actingAs($admin)
        ->get(route('business.settings.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('business/settings/index')
            ->where('business.uses_own_drivers', true)
            ->has('limits.branch_employee_usage'));
});

test('business admin can edit and update business profile', function () {
    ['admin' => $admin, 'business' => $business] = seedBusinessAdminForSettings();

    $this->actingAs($admin)
        ->get(route('business.settings.business.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('business/settings/business')
            ->where('business.name', $business->name));

    $this->actingAs($admin)
        ->post(route('business.settings.business.update'), [
            'name' => 'Restaurante Actualizado',
            'description' => 'Nueva descripción',
            'business_type' => $business->business_type,
            'phone' => '+50255550001',
            'email' => 'contacto@negocio.test',
        ])
        ->assertRedirect(route('business.settings.business.edit'));

    expect($business->fresh())
        ->name->toBe('Restaurante Actualizado')
        ->description->toBe('Nueva descripción')
        ->phone->toBe('+50255550001')
        ->email->toBe('contacto@negocio.test');
});

test('business admin can list and update accessible branches', function () {
    ['admin' => $admin, 'branch' => $branch] = seedBusinessAdminForSettings();

    $this->actingAs($admin)
        ->get(route('business.settings.branches.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('business/settings/branches/index')
            ->has('branches', 1));

    $this->actingAs($admin)
        ->get(route('business.settings.branches.edit', $branch))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('business/settings/branches/edit')
            ->where('branch.id', $branch->id));

    $this->actingAs($admin)
        ->put(route('business.settings.branches.update', $branch), [
            'name' => 'Centro actualizado',
            'phone' => '+50255550002',
            'address_text' => '5a Avenida 10-20, Comitán',
            'latitude' => 16.25,
            'longitude' => -92.13,
            'opening_hours' => BusinessHours::defaults(),
        ])
        ->assertRedirect(route('business.settings.branches.edit', $branch));

    expect($branch->fresh())
        ->name->toBe('Centro actualizado')
        ->phone->toBe('+50255550002');
});

test('business admin cannot edit branch outside their scope', function () {
    ['admin' => $admin, 'business' => $business] = seedBusinessAdminForSettings();
    $otherBranch = BusinessBranch::factory()->for($business)->create(['name' => 'Sur']);

    $this->actingAs($admin)
        ->get(route('business.settings.branches.edit', $otherBranch))
        ->assertForbidden();
});

test('business employee cannot access settings', function () {
    ['business' => $business, 'branch' => $branch] = seedBusinessAdminForSettings();
    $employee = User::factory()->businessEmployee()->create();

    BusinessUser::query()->create([
        'business_id' => $business->id,
        'user_id' => $employee->id,
        'role' => BusinessUserRole::BusinessEmployee,
        'status' => BusinessUserStatus::Active,
    ])->branches()->sync([$branch->id]);

    $this->actingAs($employee)
        ->get(route('business.settings.index'))
        ->assertForbidden();
});

test('business with platform drivers cannot access driver management', function () {
    $admin = User::factory()->businessAdmin()->create();
    $business = Business::factory()->create([
        'delivery_mode' => BusinessDeliveryMode::PlatformDrivers,
    ]);
    $branch = BusinessBranch::factory()->for($business)->create();

    BusinessUser::query()->create([
        'business_id' => $business->id,
        'user_id' => $admin->id,
        'role' => BusinessUserRole::BusinessAdmin,
        'status' => BusinessUserStatus::Active,
    ])->branches()->sync([$branch->id]);

    $this->actingAs($admin)
        ->get(route('business.drivers.index'))
        ->assertNotFound();
});
