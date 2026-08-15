<?php

use App\Enums\BusinessDeliveryMode;
use App\Enums\BusinessOperationMode;
use App\Enums\BusinessStatus;
use App\Enums\DriverAvailabilityStatus;
use App\Enums\DriverScope;
use App\Enums\OrderAddressType;
use App\Enums\OrderStatus;
use App\Models\Business;
use App\Models\BusinessBranch;
use App\Models\Customer;
use App\Models\Driver;
use App\Models\DriverCurrentLocation;
use App\Models\Order;
use App\Models\OrderAddress;
use App\Models\User;
use App\Services\Dispatch\DriverEligibilityService;
use App\Services\Dispatch\DriverRankingService;
use App\Services\Drivers\DriverLocationService;

function seedRankingContext(): array
{
    $business = Business::factory()->create([
        'operation_mode' => BusinessOperationMode::Partner,
        'status' => BusinessStatus::Active,
        'delivery_mode' => BusinessDeliveryMode::PlatformDrivers,
    ]);
    $branch = BusinessBranch::factory()->for($business)->create([
        'latitude' => 16.2514,
        'longitude' => -92.1342,
    ]);
    $customer = Customer::factory()->create();
    $order = Order::factory()->create([
        'customer_id' => $customer->id,
        'branch_id' => $branch->id,
        'order_status' => OrderStatus::Preparing,
        'assigned_driver_id' => null,
        'business_accepted_at' => now(),
        'preparation_started_at' => now(),
        'estimated_preparation_minutes' => 20,
    ]);

    OrderAddress::factory()->create([
        'order_id' => $order->id,
        'type' => OrderAddressType::Pickup,
        'latitude' => $branch->latitude,
        'longitude' => $branch->longitude,
    ]);

    return compact('business', 'branch', 'order');
}

function seedAvailablePlatformDriver(array $coords, bool $stale = false): Driver
{
    $user = User::factory()->driver()->create();
    $driver = Driver::factory()->approved()->forUser($user)->create([
        'driver_scope' => DriverScope::Platform,
        'availability_status' => DriverAvailabilityStatus::Available,
    ]);

    DriverCurrentLocation::factory()->create([
        'driver_id' => $driver->id,
        'latitude' => $coords[0],
        'longitude' => $coords[1],
        'recorded_at' => $stale ? now()->subHours(2) : now(),
    ]);

    return $driver->fresh('currentLocation');
}

test('driver can update own current location', function () {
    $user = User::factory()->driver()->create();
    $driver = Driver::factory()->approved()->forUser($user)->create([
        'availability_status' => DriverAvailabilityStatus::Available,
    ]);

    $this->actingAs($user)
        ->post(route('driver.location.update'), [
            'latitude' => 16.25,
            'longitude' => -92.13,
            'accuracy_meters' => 12,
        ])
        ->assertRedirect();

    expect($driver->fresh()->currentLocation)->not->toBeNull()
        ->and((float) $driver->fresh()->currentLocation->latitude)->toBe(16.25);
});

test('driver cannot update another driver location via own endpoint', function () {
    $user = User::factory()->driver()->create();
    Driver::factory()->approved()->forUser($user)->create([
        'availability_status' => DriverAvailabilityStatus::Available,
    ]);

    $other = User::factory()->driver()->create();
    $otherDriver = Driver::factory()->approved()->forUser($other)->create([
        'availability_status' => DriverAvailabilityStatus::Available,
    ]);

    $this->actingAs($user)
        ->post(route('driver.location.update'), [
            'latitude' => 16.25,
            'longitude' => -92.13,
        ])
        ->assertRedirect();

    expect($otherDriver->fresh()->currentLocation)->toBeNull();
});

test('stale location is not used as current', function () {
    $driver = seedAvailablePlatformDriver([16.2520, -92.1350], stale: true);

    expect(app(DriverLocationService::class)->freshLocation($driver))->toBeNull();
});

test('offline driver location is not used for ranking', function () {
    ['order' => $order] = seedRankingContext();

    $near = seedAvailablePlatformDriver([16.2515, -92.1343]);
    $near->update(['availability_status' => DriverAvailabilityStatus::Offline]);

    $far = seedAvailablePlatformDriver([16.28, -92.16]);

    $ranked = app(DriverRankingService::class)->rankEligibleDrivers(
        collect([$near->fresh(), $far->fresh()]),
        $order,
    );

    expect($ranked)->toHaveCount(1)
        ->and($ranked->first()['driver']->id)->toBe($far->id);
});

test('eligible drivers can be ordered by proximity without bypassing eligibility', function () {
    ['order' => $order, 'business' => $business] = seedRankingContext();

    $near = seedAvailablePlatformDriver([16.2515, -92.1343]);
    $far = seedAvailablePlatformDriver([16.28, -92.16]);

    $businessOnlyUser = User::factory()->driver()->create();
    $businessOnly = Driver::factory()->approved()->forUser($businessOnlyUser)->create([
        'driver_scope' => DriverScope::BusinessOnly,
        'availability_status' => DriverAvailabilityStatus::Available,
    ]);
    $businessOnly->businesses()->sync([$business->id]);
    DriverCurrentLocation::factory()->create([
        'driver_id' => $businessOnly->id,
        'latitude' => 16.2514,
        'longitude' => -92.1342,
        'recorded_at' => now(),
    ]);

    $eligibility = app(DriverEligibilityService::class);
    expect($eligibility->isDriverEligibleForOrder($businessOnly->fresh(['businesses', 'user']), $order))->toBeFalse();

    $ranked = app(DriverRankingService::class)->rankEligibleDrivers(
        collect([$far->fresh(), $businessOnly->fresh(['businesses', 'user']), $near->fresh()]),
        $order,
    );

    expect($ranked->pluck('driver.id')->all())->toBe([$near->id, $far->id])
        ->and($ranked->first()['distance_meters'])->toBeLessThan($ranked->last()['distance_meters']);
});
