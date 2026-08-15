<?php

use App\Actions\Dispatch\AcceptDeliveryOrder;
use App\Actions\Orders\AcceptBusinessOrder;
use App\Actions\Orders\CreateOrder;
use App\Enums\BusinessDeliveryMode;
use App\Enums\BusinessOperationMode;
use App\Enums\BusinessStatus;
use App\Enums\DriverAvailabilityStatus;
use App\Enums\DriverScope;
use App\Enums\OptionSelectionAction;
use App\Enums\OrderStatus;
use App\Events\Orders\DriverAssigned;
use App\Events\Orders\OrderAvailableToDriver;
use App\Events\Orders\OrderCreated;
use App\Events\Orders\OrderStatusChanged;
use App\Models\Business;
use App\Models\BusinessBranch;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Driver;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductOptionGroup;
use App\Models\ProductPrice;
use App\Models\User;
use Illuminate\Support\Facades\Event;

function seedBroadcastCatalog(): array
{
    $business = Business::factory()->create([
        'operation_mode' => BusinessOperationMode::Partner,
        'status' => BusinessStatus::Active,
        'delivery_mode' => BusinessDeliveryMode::Hybrid,
    ]);
    $branch = BusinessBranch::factory()->for($business)->create();
    $product = Product::factory()->create([
        'branch_id' => $branch->id,
        'is_active' => true,
        'is_available' => true,
        'allow_special_instructions' => true,
    ]);
    ProductPrice::factory()->create([
        'product_id' => $product->id,
        'list_price' => 100,
        'is_active' => true,
    ]);
    $group = ProductOptionGroup::factory()->removable()->create([
        'product_id' => $product->id,
    ]);
    $option = ProductOption::factory()->create([
        'option_group_id' => $group->id,
        'is_default' => true,
        'price_modifier' => 0,
    ]);

    return compact('business', 'branch', 'product', 'option');
}

function seedBroadcastCustomer(): array
{
    $user = User::factory()->customer()->create();
    $customer = Customer::factory()->for($user)->create();
    $address = CustomerAddress::factory()->create([
        'customer_id' => $customer->id,
        'is_default' => true,
    ]);

    return compact('user', 'customer', 'address');
}

test('OrderCreated is dispatched after successful order creation', function () {
    Event::fake([OrderCreated::class, OrderStatusChanged::class, OrderAvailableToDriver::class]);

    ['user' => $user, 'customer' => $customer, 'address' => $address] = seedBroadcastCustomer();
    ['branch' => $branch, 'product' => $product, 'option' => $option] = seedBroadcastCatalog();

    app(CreateOrder::class)->handle($customer, $user, [
        'branch_id' => $branch->id,
        'items' => [[
            'product_id' => $product->id,
            'quantity' => 1,
            'selected_options' => [[
                'option_id' => $option->id,
                'action' => OptionSelectionAction::Removed->value,
            ]],
        ]],
        'delivery' => [
            'source' => 'saved_address',
            'customer_address_id' => $address->id,
        ],
    ]);

    Event::assertDispatched(OrderCreated::class, function (OrderCreated $event) use ($branch): bool {
        return $event->payload['branch_id'] === $branch->id
            && $event->payload['status'] === OrderStatus::PendingBusiness->value
            && in_array('branch.'.$branch->id, $event->channels, true);
    });
});

test('OrderStatusChanged is emitted when business accepts order', function () {
    Event::fake([OrderCreated::class, OrderStatusChanged::class, OrderAvailableToDriver::class]);

    ['user' => $user, 'customer' => $customer, 'address' => $address] = seedBroadcastCustomer();
    ['branch' => $branch, 'product' => $product, 'option' => $option] = seedBroadcastCatalog();
    $businessUser = User::factory()->businessAdmin()->create();

    $order = app(CreateOrder::class)->handle($customer, $user, [
        'branch_id' => $branch->id,
        'items' => [[
            'product_id' => $product->id,
            'quantity' => 1,
            'selected_options' => [[
                'option_id' => $option->id,
                'action' => OptionSelectionAction::Removed->value,
            ]],
        ]],
        'delivery' => [
            'source' => 'saved_address',
            'customer_address_id' => $address->id,
        ],
    ]);

    Event::fake([OrderStatusChanged::class, OrderAvailableToDriver::class]);

    app(AcceptBusinessOrder::class)->handle($order, $businessUser, 20);

    Event::assertDispatched(OrderStatusChanged::class, function (OrderStatusChanged $event) use ($order): bool {
        return $event->payload['order_id'] === $order->id
            && $event->payload['status'] === OrderStatus::Preparing->value
            && $event->payload['previous_status'] === OrderStatus::PendingBusiness->value;
    });
});

test('DriverAssigned is emitted after successful assignment', function () {
    Event::fake([
        OrderCreated::class,
        OrderStatusChanged::class,
        OrderAvailableToDriver::class,
        DriverAssigned::class,
    ]);

    ['user' => $user, 'customer' => $customer, 'address' => $address] = seedBroadcastCustomer();
    ['branch' => $branch, 'product' => $product, 'option' => $option] = seedBroadcastCatalog();
    $businessUser = User::factory()->businessAdmin()->create();
    $driverUser = User::factory()->driver()->create();
    $driver = Driver::factory()->approved()->forUser($driverUser)->create([
        'driver_scope' => DriverScope::Platform,
        'availability_status' => DriverAvailabilityStatus::Available,
    ]);

    $order = app(CreateOrder::class)->handle($customer, $user, [
        'branch_id' => $branch->id,
        'items' => [[
            'product_id' => $product->id,
            'quantity' => 1,
            'selected_options' => [[
                'option_id' => $option->id,
                'action' => OptionSelectionAction::Removed->value,
            ]],
        ]],
        'delivery' => [
            'source' => 'saved_address',
            'customer_address_id' => $address->id,
        ],
    ]);

    app(AcceptBusinessOrder::class)->handle($order, $businessUser, 15);

    Event::fake([DriverAssigned::class, OrderStatusChanged::class]);

    app(AcceptDeliveryOrder::class)->handle($order->fresh(), $driver, $driverUser);

    Event::assertDispatched(DriverAssigned::class, function (DriverAssigned $event) use ($order, $driver): bool {
        return $event->payload['order_id'] === $order->id
            && $event->payload['assigned_driver_id'] === $driver->id
            && in_array('driver.'.$driver->id, $event->channels, true);
    });
});

test('platform operated order created notifies admin channel', function () {
    Event::fake([OrderCreated::class]);

    ['user' => $user, 'customer' => $customer, 'address' => $address] = seedBroadcastCustomer();
    $business = Business::factory()->create([
        'operation_mode' => BusinessOperationMode::PlatformOperated,
        'status' => BusinessStatus::Active,
        'delivery_mode' => BusinessDeliveryMode::PlatformDrivers,
    ]);
    $branch = BusinessBranch::factory()->for($business)->create();
    $product = Product::factory()->create([
        'branch_id' => $branch->id,
        'is_active' => true,
        'is_available' => true,
    ]);
    ProductPrice::factory()->create([
        'product_id' => $product->id,
        'list_price' => 80,
        'is_active' => true,
    ]);

    app(CreateOrder::class)->handle($customer, $user, [
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

    Event::assertDispatched(OrderCreated::class, function (OrderCreated $event): bool {
        return in_array('admin', $event->channels, true)
            && $event->payload['status'] === OrderStatus::PendingPlatform->value
            && ! collect($event->channels)->contains(fn (string $channel): bool => str_starts_with($channel, 'business.'))
            && ! collect($event->channels)->contains(fn (string $channel): bool => str_starts_with($channel, 'branch.'));
    });
});
