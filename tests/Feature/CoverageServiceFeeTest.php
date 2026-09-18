<?php

use App\Actions\Orders\CreateOrder;
use App\Enums\BusinessOperationMode;
use App\Enums\BusinessStatus;
use App\Enums\CoverageScopeType;
use App\Enums\OrderAddressSource;
use App\Enums\UserRole;
use App\Models\Business;
use App\Models\BusinessBranch;
use App\Models\CoverageZone;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\ServiceFeeDistanceSetting;
use App\Models\User;
use App\Services\Geo\CoverageService;
use App\Services\Geo\DistanceService;
use App\Services\Geo\ServiceFeeDistanceCalculator;
use App\Support\GeoPoint;

test('creating an order applies distance-based service fee', function () {
    ServiceFeeDistanceSetting::query()->delete();
    ServiceFeeDistanceSetting::factory()->create([
        'base_meters' => 1000,
        'base_fee' => 50,
        'step_meters' => 500,
        'step_fee' => 5,
    ]);

    config(['maps.distance_mode' => 'straight_line']);

    $user = User::factory()->customer()->create();
    $customer = Customer::factory()->forUser($user)->create();

    $business = Business::factory()->create([
        'status' => BusinessStatus::Active,
        'operation_mode' => BusinessOperationMode::Partner,
    ]);
    $branch = BusinessBranch::factory()->for($business)->create([
        'latitude' => 16.2514,
        'longitude' => -92.1342,
    ]);

    // No platform zones => delivery allowed anywhere (rollout-safe).
    $product = Product::factory()->create(['branch_id' => $branch->id, 'is_active' => true]);
    ProductPrice::factory()->create(['product_id' => $product->id, 'list_price' => 50]);

    // ~1.5 km north of the branch (straight-line).
    $deliveryLat = 16.2649;
    $deliveryLng = -92.1342;

    $meters = app(DistanceService::class)->haversineMeters(
        GeoPoint::make(16.2514, -92.1342),
        GeoPoint::make($deliveryLat, $deliveryLng),
    );

    expect($meters)->toBeGreaterThan(1000)->toBeLessThanOrEqual(2000);

    $order = app(CreateOrder::class)->handle($customer, $user, [
        'branch_id' => $branch->id,
        'items' => [
            ['product_id' => $product->id, 'quantity' => 1, 'selected_options' => []],
        ],
        'delivery' => [
            'source' => OrderAddressSource::Temporary->value,
            'address_text' => 'A ~1.5 km',
            'latitude' => $deliveryLat,
            'longitude' => $deliveryLng,
        ],
    ]);

    $expectedFee = app(ServiceFeeDistanceCalculator::class)
        ->calculate($meters);

    expect((string) $order->service_fee)->toBe($expectedFee);
});

test('service fee quote endpoint returns distance pricing', function () {
    ServiceFeeDistanceSetting::query()->delete();
    ServiceFeeDistanceSetting::factory()->create();
    config(['maps.distance_mode' => 'straight_line']);

    $business = Business::factory()->create(['status' => BusinessStatus::Active]);
    $branch = BusinessBranch::factory()->for($business)->create([
        'latitude' => 16.2514,
        'longitude' => -92.1342,
    ]);

    $this->postJson(route('service-fee.quote'), [
        'latitude' => 16.2514,
        'longitude' => -92.1342,
        'branch_id' => $branch->id,
    ])
        ->assertOk()
        ->assertJsonPath('service_fee', '50.00')
        ->assertJsonPath('distance_meters', 0)
        ->assertJsonPath('covered', true);
});

test('service fee quote marks points outside platform coverage', function () {
    CoverageZone::factory()->create([
        'scope_type' => CoverageScopeType::Platform,
        'center_latitude' => 16.2514,
        'center_longitude' => -92.1342,
        'radius_meters' => 1000,
        'is_active' => true,
    ]);

    $this->postJson(route('service-fee.quote'), [
        'latitude' => 16.40,
        'longitude' => -92.40,
    ])
        ->assertOk()
        ->assertJsonPath('covered', false)
        ->assertJsonPath(
            'message',
            CoverageService::UNAVAILABLE_MESSAGE,
        );
});

test('admin can update the distance service tariff', function () {
    ServiceFeeDistanceSetting::query()->delete();
    $tariff = ServiceFeeDistanceSetting::factory()->create();

    $admin = User::factory()->systemAdmin()->create();

    $this->actingAs($admin)
        ->put(route('admin.coverage.tariff.update'), [
            'base_meters' => 2000,
            'base_fee' => 45,
            'step_meters' => 1000,
            'step_fee' => 8,
        ])
        ->assertRedirect();

    $tariff->refresh();

    expect((int) $tariff->base_meters)->toBe(2000)
        ->and((string) $tariff->base_fee)->toBe('45.00')
        ->and((int) $tariff->step_meters)->toBe(1000)
        ->and((string) $tariff->step_fee)->toBe('8.00');
});

test('admin can create a coverage zone without a zone service fee', function () {
    $admin = User::factory()->systemAdmin()->create();

    $this->actingAs($admin)
        ->post(route('admin.coverage.store'), [
            'name' => 'Comitán',
            'scope_type' => CoverageScopeType::Platform->value,
            'scope_id' => null,
            'zone_type' => 'radius',
            'center_latitude' => 16.2514,
            'center_longitude' => -92.1342,
            'radius_meters' => 3000,
            'is_active' => true,
        ])
        ->assertRedirect();

    $zone = CoverageZone::query()->where('name', 'Comitán')->first();

    expect($zone)->not->toBeNull()
        ->and($zone->radius_meters)->toBe(3000);
});

test('admin coverage index keeps the shared maps api key', function () {
    config(['maps.browser_api_key' => 'test-browser-key']);

    $admin = User::factory()->systemAdmin()->create();

    $this->actingAs($admin)
        ->get(route('admin.coverage.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/coverage/index')
            ->where('maps.browser_api_key', 'test-browser-key')
            ->has('serviceFeeTariff.base_meters')
            ->has('serviceFeeTariff.base_fee'));
});

test('admin without system admin role cannot manage coverage', function () {
    $user = User::factory()->create(['role' => UserRole::Customer]);

    $this->actingAs($user)
        ->post(route('admin.coverage.store'), [
            'name' => 'Hack',
            'scope_type' => CoverageScopeType::Platform->value,
            'zone_type' => 'radius',
            'center_latitude' => 16.25,
            'center_longitude' => -92.13,
            'radius_meters' => 1000,
        ])
        ->assertForbidden();
});
