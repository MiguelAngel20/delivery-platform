<?php

use App\Actions\Orders\CreateOrder;
use App\Enums\BusinessOperationMode;
use App\Enums\BusinessStatus;
use App\Enums\CoverageScopeType;
use App\Enums\OrderAddressSource;
use App\Models\Business;
use App\Models\BusinessBranch;
use App\Models\CoverageZone;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\User;
use Illuminate\Validation\ValidationException;

test('checkout rejects delivery outside coverage', function () {
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

    CoverageZone::factory()->create([
        'scope_type' => CoverageScopeType::Platform,
        'center_latitude' => 16.2514,
        'center_longitude' => -92.1342,
        'radius_meters' => 1000,
        'is_active' => true,
    ]);

    $product = Product::factory()->create(['branch_id' => $branch->id, 'is_active' => true]);
    ProductPrice::factory()->create(['product_id' => $product->id, 'list_price' => 50]);

    expect(fn () => app(CreateOrder::class)->handle($customer, $user, [
        'branch_id' => $branch->id,
        'items' => [
            ['product_id' => $product->id, 'quantity' => 1, 'selected_options' => []],
        ],
        'delivery' => [
            'source' => OrderAddressSource::Temporary->value,
            'address_text' => 'Lejos',
            'latitude' => 16.40,
            'longitude' => -92.40,
        ],
    ]))->toThrow(ValidationException::class);
});

test('checkout inside coverage stores logistics snapshot', function () {
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

    CoverageZone::factory()->create([
        'scope_type' => CoverageScopeType::Platform,
        'center_latitude' => 16.2514,
        'center_longitude' => -92.1342,
        'radius_meters' => 10000,
        'is_active' => true,
    ]);

    $product = Product::factory()->create(['branch_id' => $branch->id, 'is_active' => true]);
    ProductPrice::factory()->create(['product_id' => $product->id, 'list_price' => 50]);

    $address = CustomerAddress::factory()->create([
        'customer_id' => $customer->id,
        'latitude' => 16.2550,
        'longitude' => -92.1300,
        'is_active' => true,
    ]);

    config(['maps.distance_mode' => 'straight_line']);

    $order = app(CreateOrder::class)->handle($customer, $user, [
        'branch_id' => $branch->id,
        'items' => [
            ['product_id' => $product->id, 'quantity' => 1, 'selected_options' => []],
        ],
        'delivery' => [
            'source' => OrderAddressSource::SavedAddress->value,
            'customer_address_id' => $address->id,
        ],
    ]);

    expect($order->logistics)->not->toBeNull()
        ->and($order->logistics->pickup_to_delivery_distance_meters)->toBeGreaterThan(0)
        ->and($order->deliveryAddress?->latitude)->not->toBeNull()
        ->and($order->pickupAddress?->latitude)->not->toBeNull();
});
