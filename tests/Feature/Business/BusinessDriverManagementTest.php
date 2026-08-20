<?php

use App\Enums\BusinessDeliveryMode;
use App\Enums\BusinessUserRole;
use App\Enums\BusinessUserStatus;
use App\Enums\DriverScope;
use App\Enums\UserRole;
use App\Models\Business;
use App\Models\BusinessBranch;
use App\Models\BusinessUser;
use App\Models\Driver;
use App\Models\User;

function seedBusinessAdminWithOwnDrivers(): array
{
    $admin = User::factory()->businessAdmin()->create();
    $business = Business::factory()->create([
        'delivery_mode' => BusinessDeliveryMode::OwnDrivers,
    ]);
    $branchA = BusinessBranch::factory()->for($business)->create(['name' => 'Centro']);
    $branchB = BusinessBranch::factory()->for($business)->create(['name' => 'Norte']);

    BusinessUser::query()->create([
        'business_id' => $business->id,
        'user_id' => $admin->id,
        'role' => BusinessUserRole::BusinessAdmin,
        'status' => BusinessUserStatus::Active,
    ])->branches()->sync([$branchA->id]);

    return compact('admin', 'business', 'branchA', 'branchB');
}

test('business admin can list drivers scoped to their branches', function () {
    ['admin' => $admin, 'business' => $business, 'branchA' => $branchA, 'branchB' => $branchB] = seedBusinessAdminWithOwnDrivers();

    $scopedDriverUser = User::factory()->driver()->create();
    $scopedDriver = Driver::factory()->approved()->forUser($scopedDriverUser)->create([
        'driver_scope' => DriverScope::BusinessOnly,
    ]);
    $scopedDriver->businesses()->attach($business->id);
    $scopedDriver->branches()->attach($branchA->id);

    $otherBranchDriverUser = User::factory()->driver()->create();
    $otherBranchDriver = Driver::factory()->approved()->forUser($otherBranchDriverUser)->create([
        'driver_scope' => DriverScope::BusinessOnly,
    ]);
    $otherBranchDriver->businesses()->attach($business->id);
    $otherBranchDriver->branches()->attach([$branchA->id, $branchB->id]);

    $this->actingAs($admin)
        ->get(route('business.drivers.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('business/drivers/index')
            ->has('drivers', 1)
            ->where('drivers.0.id', $scopedDriver->id));
});

test('business admin can create a driver for their branch', function () {
    ['admin' => $admin, 'branchA' => $branchA] = seedBusinessAdminWithOwnDrivers();

    $response = $this->actingAs($admin)
        ->post(route('business.drivers.store'), [
            'first_name' => 'Pedro',
            'last_name' => 'Repartidor',
            'email' => 'pedro.repartidor@ride.test',
            'phone' => '+50255559101',
            'branch_ids' => [$branchA->id],
        ]);

    $driver = Driver::query()
        ->whereHas('user', fn ($query) => $query->where('email', 'pedro.repartidor@ride.test'))
        ->first();

    expect($driver)->not->toBeNull()
        ->and($driver?->driver_scope)->toBe(DriverScope::BusinessOnly)
        ->and($driver?->user?->role)->toBe(UserRole::Driver)
        ->and($driver?->branches()->pluck('business_branches.id')->all())->toBe([$branchA->id]);

    $response->assertRedirect(route('business.drivers.edit', $driver));
});

test('business admin cannot assign driver to branch outside their scope', function () {
    ['admin' => $admin, 'branchB' => $branchB] = seedBusinessAdminWithOwnDrivers();

    $this->actingAs($admin)
        ->post(route('business.drivers.store'), [
            'first_name' => 'Ana',
            'last_name' => 'Reparto',
            'email' => 'ana.reparto@ride.test',
            'phone' => '+50255559102',
            'branch_ids' => [$branchB->id],
        ])
        ->assertSessionHasErrors('branch_ids.0');
});

test('business admin can update driver within their branch scope', function () {
    ['admin' => $admin, 'business' => $business, 'branchA' => $branchA] = seedBusinessAdminWithOwnDrivers();

    $driverUser = User::factory()->driver()->create();
    $driver = Driver::factory()->approved()->forUser($driverUser)->create([
        'driver_scope' => DriverScope::BusinessOnly,
    ]);
    $driver->businesses()->attach($business->id);
    $driver->branches()->attach($branchA->id);

    $this->actingAs($admin)
        ->put(route('business.drivers.update', $driver), [
            'first_name' => 'Pedro',
            'last_name' => 'Actualizado',
            'email' => 'pedro.actualizado@ride.test',
            'phone' => '+50255559103',
            'branch_ids' => [$branchA->id],
        ])
        ->assertRedirect(route('business.drivers.edit', $driver));

    expect($driver->fresh()->user)
        ->first_name->toBe('Pedro')
        ->last_name->toBe('Actualizado')
        ->email->toBe('pedro.actualizado@ride.test');
});

test('business admin cannot edit driver assigned to branches outside their scope', function () {
    ['admin' => $admin, 'business' => $business, 'branchA' => $branchA, 'branchB' => $branchB] = seedBusinessAdminWithOwnDrivers();

    $driverUser = User::factory()->driver()->create();
    $driver = Driver::factory()->approved()->forUser($driverUser)->create([
        'driver_scope' => DriverScope::BusinessOnly,
    ]);
    $driver->businesses()->attach($business->id);
    $driver->branches()->attach([$branchA->id, $branchB->id]);

    $this->actingAs($admin)
        ->get(route('business.drivers.edit', $driver))
        ->assertNotFound();
});
