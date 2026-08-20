<?php

use App\Enums\BusinessDeliveryMode;
use App\Enums\DriverScope;
use App\Enums\UserRole;
use App\Models\Business;
use App\Models\BusinessBranch;
use App\Models\Driver;
use App\Models\User;

test('system admin can list drivers of a business', function () {
    $admin = User::factory()->systemAdmin()->create();
    $business = Business::factory()->create([
        'delivery_mode' => BusinessDeliveryMode::OwnDrivers,
    ]);
    $branch = BusinessBranch::factory()->for($business)->create();
    $driverUser = User::factory()->driver()->create();
    $driver = Driver::factory()->approved()->forUser($driverUser)->create([
        'driver_scope' => DriverScope::BusinessOnly,
    ]);
    $driver->businesses()->attach($business->id);
    $driver->branches()->attach($branch->id);

    $this->actingAs($admin)
        ->get(route('admin.businesses.drivers.index', $business))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/businesses/drivers/index')
            ->has('drivers', 1));
});

test('system admin can create a business driver assigned to a branch', function () {
    $admin = User::factory()->systemAdmin()->create();
    $business = Business::factory()->create([
        'delivery_mode' => BusinessDeliveryMode::OwnDrivers,
    ]);
    $branch = BusinessBranch::factory()->for($business)->create();

    $response = $this->actingAs($admin)
        ->post(route('admin.businesses.drivers.store', $business), [
            'first_name' => 'Mario',
            'last_name' => 'Repartidor',
            'email' => 'mario.repartidor@ride.test',
            'phone' => '+50255559001',
            'branch_ids' => [$branch->id],
        ]);

    $driver = Driver::query()
        ->whereHas('user', fn ($query) => $query->where('email', 'mario.repartidor@ride.test'))
        ->first();

    expect($driver)->not->toBeNull()
        ->and($driver?->driver_scope)->toBe(DriverScope::BusinessOnly)
        ->and($driver?->user?->role)->toBe(UserRole::Driver)
        ->and($driver?->businesses()->pluck('businesses.id')->all())->toBe([$business->id])
        ->and($driver?->branches()->pluck('business_branches.id')->all())->toBe([$branch->id]);

    $response->assertRedirect(route('admin.businesses.drivers.edit', [$business, $driver]));
});

test('system admin cannot assign a driver to a branch of another business', function () {
    $admin = User::factory()->systemAdmin()->create();
    $business = Business::factory()->create([
        'delivery_mode' => BusinessDeliveryMode::OwnDrivers,
    ]);
    BusinessBranch::factory()->for($business)->create();
    $otherBranch = BusinessBranch::factory()->create();

    $this->actingAs($admin)
        ->post(route('admin.businesses.drivers.store', $business), [
            'first_name' => 'Ana',
            'last_name' => 'Reparto',
            'email' => 'ana.reparto@ride.test',
            'phone' => '+50255559002',
            'branch_ids' => [$otherBranch->id],
        ])
        ->assertSessionHasErrors('branch_ids.0');
});

test('system admin can change driver branches', function () {
    $admin = User::factory()->systemAdmin()->create();
    $business = Business::factory()->create([
        'delivery_mode' => BusinessDeliveryMode::Hybrid,
    ]);
    $branchA = BusinessBranch::factory()->for($business)->create();
    $branchB = BusinessBranch::factory()->for($business)->create();
    $driverUser = User::factory()->driver()->create([
        'first_name' => 'Luis',
        'last_name' => 'Moto',
        'email' => 'luis.moto@ride.test',
        'phone' => '+50255559003',
    ]);
    $driver = Driver::factory()->approved()->forUser($driverUser)->create([
        'driver_scope' => DriverScope::BusinessOnly,
    ]);
    $driver->businesses()->attach($business->id);
    $driver->branches()->attach($branchA->id);

    $this->actingAs($admin)
        ->put(route('admin.businesses.drivers.update', [$business, $driver]), [
            'first_name' => $driverUser->first_name,
            'last_name' => $driverUser->last_name,
            'email' => $driverUser->email,
            'phone' => $driverUser->phone,
            'branch_ids' => [$branchB->id],
        ])
        ->assertRedirect();

    expect($driver->fresh()->branches()->pluck('business_branches.id')->all())->toBe([$branchB->id]);
});

test('system admin can detach a driver from a business', function () {
    $admin = User::factory()->systemAdmin()->create();
    $business = Business::factory()->create([
        'delivery_mode' => BusinessDeliveryMode::OwnDrivers,
    ]);
    $branch = BusinessBranch::factory()->for($business)->create();
    $driver = Driver::factory()->approved()->create([
        'driver_scope' => DriverScope::BusinessOnly,
    ]);
    $driver->businesses()->attach($business->id);
    $driver->branches()->attach($branch->id);

    $this->actingAs($admin)
        ->delete(route('admin.businesses.drivers.destroy', [$business, $driver]))
        ->assertRedirect(route('admin.businesses.drivers.index', $business));

    expect($driver->fresh()->businesses)->toHaveCount(0)
        ->and($driver->fresh()->branches)->toHaveCount(0);
});

test('creating a driver requires at least one branch', function () {
    $admin = User::factory()->systemAdmin()->create();
    $business = Business::factory()->create([
        'delivery_mode' => BusinessDeliveryMode::OwnDrivers,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.businesses.drivers.store', $business), [
            'first_name' => 'Sin',
            'last_name' => 'Sucursal',
            'email' => 'sin.sucursal@ride.test',
            'phone' => '+50255559004',
            'branch_ids' => [],
        ])
        ->assertSessionHasErrors('branch_ids');
});
