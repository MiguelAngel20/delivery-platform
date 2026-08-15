<?php

use App\Actions\Dispatch\AcceptDeliveryOrder;
use App\Actions\Orders\AcceptBusinessOrder;
use App\Actions\Orders\CreateOrder;
use App\Enums\BusinessDeliveryMode;
use App\Enums\BusinessOperationMode;
use App\Enums\BusinessStatus;
use App\Enums\BusinessUserRole;
use App\Enums\BusinessUserStatus;
use App\Enums\DriverAvailabilityStatus;
use App\Enums\DriverScope;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Models\Business;
use App\Models\BusinessBranch;
use App\Models\BusinessUser;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Driver;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\User;
use App\Services\Dispatch\DriverEligibilityService;

function seedPlatformCustomer(): array
{
    $user = User::factory()->customer()->create();
    $customer = Customer::factory()->for($user)->create();
    $address = CustomerAddress::factory()->create([
        'customer_id' => $customer->id,
        'is_default' => true,
    ]);

    return compact('user', 'customer', 'address');
}

function seedPlatformCatalog(array $overrides = []): array
{
    $business = Business::factory()->create([
        'operation_mode' => BusinessOperationMode::PlatformOperated,
        'status' => BusinessStatus::Active,
        'delivery_mode' => BusinessDeliveryMode::PlatformDrivers,
        ...$overrides,
    ]);
    $branch = BusinessBranch::factory()->for($business)->create();
    $product = Product::factory()->create([
        'branch_id' => $branch->id,
        'name' => 'Hamburguesa',
        'is_active' => true,
        'is_available' => true,
    ]);
    ProductPrice::factory()->create([
        'product_id' => $product->id,
        'list_price' => 105,
        'acquisition_cost' => 100,
        'is_active' => true,
    ]);

    return compact('business', 'branch', 'product');
}

test('system admin can create platform-operated business', function () {
    $admin = User::factory()->systemAdmin()->create();

    $this->actingAs($admin)
        ->post(route('admin.businesses.store'), [
            'name' => 'Taquería El Centro',
            'business_type' => 'Restaurante',
            'operation_mode' => BusinessOperationMode::PlatformOperated->value,
            'delivery_mode' => BusinessDeliveryMode::PlatformDrivers->value,
            'status' => BusinessStatus::Active->value,
        ])
        ->assertRedirect();

    $business = Business::query()->where('name', 'Taquería El Centro')->first();

    expect($business)->not->toBeNull()
        ->and($business?->operation_mode)->toBe(BusinessOperationMode::PlatformOperated);
});

test('business user cannot manage platform-operated catalog', function () {
    $admin = User::factory()->businessAdmin()->create();
    ['business' => $business, 'branch' => $branch] = seedPlatformCatalog();

    BusinessUser::query()->create([
        'business_id' => $business->id,
        'user_id' => $admin->id,
        'role' => BusinessUserRole::BusinessAdmin,
        'status' => BusinessUserStatus::Active,
    ]);

    $this->actingAs($admin)
        ->post(route('business.categories.store'), [
            'branch_id' => $branch->id,
            'name' => 'No permitido',
        ])
        ->assertForbidden();
});

test('customer can view published platform-operated business', function () {
    ['business' => $business] = seedPlatformCatalog();

    $this->get(route('restaurants.show', $business->slug))
        ->assertOk();
});

test('platform-operated order goes to system admin not business queue', function () {
    ['user' => $user, 'customer' => $customer, 'address' => $address] = seedPlatformCustomer();
    ['business' => $business, 'branch' => $branch, 'product' => $product] = seedPlatformCatalog();

    $businessUser = User::factory()->businessAdmin()->create();
    BusinessUser::query()->create([
        'business_id' => $business->id,
        'user_id' => $businessUser->id,
        'role' => BusinessUserRole::BusinessAdmin,
        'status' => BusinessUserStatus::Active,
    ]);

    $order = app(CreateOrder::class)->handle($customer, $user, [
        'branch_id' => $branch->id,
        'items' => [[
            'product_id' => $product->id,
            'quantity' => 1,
            'selected_options' => [],
        ]],
        'delivery' => [
            'source' => 'saved_address',
            'customer_address_id' => $address->id,
        ],
    ]);

    expect($order->order_status)->toBe(OrderStatus::PendingPlatform)
        ->and($order->operation_mode)->toBe(BusinessOperationMode::PlatformOperated)
        ->and($order->type)->toBe(OrderType::Business)
        ->and((string) $order->items->first()?->unit_acquisition_cost)->toBe('100.00')
        ->and((string) $order->financial?->business_amount)->toBe('100.00')
        ->and((string) $order->financial?->platform_earning)->toBe('5.00');

    $this->actingAs($businessUser)
        ->get(route('business.orders.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('newCount', 0));

    $admin = User::factory()->systemAdmin()->create();

    $this->actingAs($admin)
        ->get(route('admin.orders.index', ['filter' => 'pending']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('orders.data', 1));
});

test('admin confirms platform order and it enters dispatch', function () {
    ['user' => $user, 'customer' => $customer, 'address' => $address] = seedPlatformCustomer();
    ['branch' => $branch, 'product' => $product] = seedPlatformCatalog();
    $admin = User::factory()->systemAdmin()->create();

    $order = app(CreateOrder::class)->handle($customer, $user, [
        'branch_id' => $branch->id,
        'items' => [[
            'product_id' => $product->id,
            'quantity' => 1,
            'selected_options' => [],
        ]],
        'delivery' => [
            'source' => 'saved_address',
            'customer_address_id' => $address->id,
        ],
    ]);

    $this->actingAs($admin)
        ->post(route('admin.orders.confirm', $order), [
            'estimated_preparation_minutes' => 25,
        ])
        ->assertRedirect();

    $order->refresh();

    expect($order->order_status)->toBe(OrderStatus::Preparing)
        ->and($order->estimated_preparation_minutes)->toBe(25);

    $driverUser = User::factory()->driver()->create();
    $driver = Driver::factory()->approved()->forUser($driverUser)->create([
        'driver_scope' => DriverScope::Platform,
        'availability_status' => DriverAvailabilityStatus::Available,
    ]);

    expect(app(DriverEligibilityService::class)->isDriverEligibleForOrder($driver, $order->fresh()))
        ->toBeTrue();

    app(AcceptDeliveryOrder::class)->handle($order->fresh(), $driver, $driverUser);

    expect($order->fresh()->assigned_driver_id)->toBe($driver->id);
});

test('business-only driver is not eligible for platform-operated orders', function () {
    ['user' => $user, 'customer' => $customer, 'address' => $address] = seedPlatformCustomer();
    ['business' => $business, 'branch' => $branch, 'product' => $product] = seedPlatformCatalog();

    $order = app(CreateOrder::class)->handle($customer, $user, [
        'branch_id' => $branch->id,
        'items' => [[
            'product_id' => $product->id,
            'quantity' => 1,
            'selected_options' => [],
        ]],
        'delivery' => [
            'source' => 'saved_address',
            'customer_address_id' => $address->id,
        ],
    ]);

    app(AcceptBusinessOrder::class)->handle(
        $order,
        User::factory()->systemAdmin()->create(),
        20,
    );

    $driverUser = User::factory()->driver()->create();
    $driver = Driver::factory()->approved()->forUser($driverUser)->create([
        'driver_scope' => DriverScope::BusinessOnly,
        'availability_status' => DriverAvailabilityStatus::Available,
    ]);
    $driver->businesses()->attach($business->id);

    expect(app(DriverEligibilityService::class)->isDriverEligibleForOrder($driver, $order->fresh()))
        ->toBeFalse();
});

test('business user cannot accept platform-operated order', function () {
    ['user' => $user, 'customer' => $customer, 'address' => $address] = seedPlatformCustomer();
    ['business' => $business, 'branch' => $branch, 'product' => $product] = seedPlatformCatalog();

    $businessUser = User::factory()->businessAdmin()->create();
    BusinessUser::query()->create([
        'business_id' => $business->id,
        'user_id' => $businessUser->id,
        'role' => BusinessUserRole::BusinessAdmin,
        'status' => BusinessUserStatus::Active,
    ]);

    $order = app(CreateOrder::class)->handle($customer, $user, [
        'branch_id' => $branch->id,
        'items' => [[
            'product_id' => $product->id,
            'quantity' => 1,
            'selected_options' => [],
        ]],
        'delivery' => [
            'source' => 'saved_address',
            'customer_address_id' => $address->id,
        ],
    ]);

    $this->actingAs($businessUser)
        ->post(route('business.orders.accept', $order), [
            'estimated_preparation_minutes' => 15,
        ])
        ->assertForbidden();
});
