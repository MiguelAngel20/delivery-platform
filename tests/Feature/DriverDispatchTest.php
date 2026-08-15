<?php

use App\Actions\Dispatch\AcceptDeliveryOrder;
use App\Actions\Dispatch\DeliverOrder;
use App\Actions\Dispatch\MarkDriverArrived;
use App\Actions\Dispatch\PickupOrder;
use App\Actions\Dispatch\RejectDeliveryOffer;
use App\Actions\Dispatch\StartDelivery;
use App\Actions\Orders\AcceptBusinessOrder;
use App\Actions\Orders\MarkOrderReady;
use App\Enums\BusinessDeliveryMode;
use App\Enums\BusinessOperationMode;
use App\Enums\BusinessStatus;
use App\Enums\DeliveryTripStatus;
use App\Enums\DriverAssignmentStatus;
use App\Enums\DriverAvailabilityStatus;
use App\Enums\DriverScope;
use App\Enums\OrderStatus;
use App\Models\Business;
use App\Models\BusinessBranch;
use App\Models\Customer;
use App\Models\DeliveryTrip;
use App\Models\DeliveryTripOrder;
use App\Models\Driver;
use App\Models\DriverAssignment;
use App\Models\Order;
use App\Models\User;
use App\Services\Dispatch\AvailableOrdersQuery;
use App\Services\Dispatch\DriverEligibilityService;
use Illuminate\Validation\ValidationException;

function seedDispatchBusiness(BusinessDeliveryMode $mode = BusinessDeliveryMode::Hybrid): array
{
    $business = Business::factory()->create([
        'operation_mode' => BusinessOperationMode::Partner,
        'status' => BusinessStatus::Active,
        'delivery_mode' => $mode,
    ]);
    $branch = BusinessBranch::factory()->for($business)->create();

    return compact('business', 'branch');
}

function seedDispatchOrder(BusinessBranch $branch, OrderStatus $status = OrderStatus::Preparing): Order
{
    $customer = Customer::factory()->create();

    return Order::factory()->create([
        'customer_id' => $customer->id,
        'branch_id' => $branch->id,
        'order_status' => $status,
        'assigned_driver_id' => null,
        'business_accepted_at' => now(),
        'preparation_started_at' => now(),
        'estimated_preparation_minutes' => 20,
        'service_fee' => 50,
    ]);
}

function seedPlatformDriver(): array
{
    $user = User::factory()->driver()->create();
    $driver = Driver::factory()->approved()->forUser($user)->create([
        'driver_scope' => DriverScope::Platform,
        'availability_status' => DriverAvailabilityStatus::Available,
    ]);

    return compact('user', 'driver');
}

function seedBusinessOnlyDriver(Business $business): array
{
    $user = User::factory()->driver()->create();
    $driver = Driver::factory()->approved()->forUser($user)->create([
        'driver_scope' => DriverScope::BusinessOnly,
        'availability_status' => DriverAvailabilityStatus::Available,
    ]);
    $driver->businesses()->sync([$business->id]);

    return compact('user', 'driver');
}

test('business only driver sees only associated business orders', function () {
    ['business' => $business, 'branch' => $branch] = seedDispatchBusiness(BusinessDeliveryMode::Hybrid);
    ['business' => $otherBusiness, 'branch' => $otherBranch] = seedDispatchBusiness(BusinessDeliveryMode::Hybrid);
    ['driver' => $driver] = seedBusinessOnlyDriver($business);

    $visible = seedDispatchOrder($branch);
    seedDispatchOrder($otherBranch);

    $orders = app(AvailableOrdersQuery::class)->forDriver($driver);

    expect($orders)->toHaveCount(1)
        ->and($orders->first()?->id)->toBe($visible->id);
});

test('platform driver sees eligible platform and hybrid orders', function () {
    ['branch' => $platformBranch] = seedDispatchBusiness(BusinessDeliveryMode::PlatformDrivers);
    ['branch' => $hybridBranch] = seedDispatchBusiness(BusinessDeliveryMode::Hybrid);
    ['branch' => $ownBranch] = seedDispatchBusiness(BusinessDeliveryMode::OwnDrivers);
    ['driver' => $driver] = seedPlatformDriver();

    $platformOrder = seedDispatchOrder($platformBranch);
    $hybridOrder = seedDispatchOrder($hybridBranch);
    seedDispatchOrder($ownBranch);

    $ids = app(AvailableOrdersQuery::class)->forDriver($driver)->pluck('id');

    expect($ids)->toContain($platformOrder->id)
        ->and($ids)->toContain($hybridOrder->id)
        ->and($ids)->not->toContain(
            Order::query()->where('branch_id', $ownBranch->id)->value('id'),
        );
});

test('own drivers mode excludes platform driver', function () {
    ['business' => $business, 'branch' => $branch] = seedDispatchBusiness(BusinessDeliveryMode::OwnDrivers);
    ['driver' => $platform] = seedPlatformDriver();
    ['driver' => $own] = seedBusinessOnlyDriver($business);
    $order = seedDispatchOrder($branch);

    expect(app(DriverEligibilityService::class)->isDriverEligibleForOrder($platform, $order))->toBeFalse()
        ->and(app(DriverEligibilityService::class)->isDriverEligibleForOrder($own, $order))->toBeTrue();
});

test('platform drivers mode excludes business only driver', function () {
    ['business' => $business, 'branch' => $branch] = seedDispatchBusiness(BusinessDeliveryMode::PlatformDrivers);
    ['driver' => $platform] = seedPlatformDriver();
    ['driver' => $own] = seedBusinessOnlyDriver($business);
    $order = seedDispatchOrder($branch);

    expect(app(DriverEligibilityService::class)->isDriverEligibleForOrder($platform, $order))->toBeTrue()
        ->and(app(DriverEligibilityService::class)->isDriverEligibleForOrder($own, $order))->toBeFalse();
});

test('hybrid allows own drivers and platform fallback', function () {
    ['business' => $business, 'branch' => $branch] = seedDispatchBusiness(BusinessDeliveryMode::Hybrid);
    ['driver' => $platform] = seedPlatformDriver();
    ['driver' => $own] = seedBusinessOnlyDriver($business);
    $order = seedDispatchOrder($branch);

    expect(app(DriverEligibilityService::class)->isDriverEligibleForOrder($platform, $order))->toBeTrue()
        ->and(app(DriverEligibilityService::class)->isDriverEligibleForOrder($own, $order))->toBeTrue();
});

test('driver can accept second order from same branch', function () {
    ['branch' => $branch] = seedDispatchBusiness();
    ['user' => $user, 'driver' => $driver] = seedPlatformDriver();
    $first = seedDispatchOrder($branch);
    $second = seedDispatchOrder($branch);

    app(AcceptDeliveryOrder::class)->handle($first, $driver, $user);
    app(AcceptDeliveryOrder::class)->handle($second, $driver->fresh(), $user);

    expect($first->fresh()->assigned_driver_id)->toBe($driver->id)
        ->and($second->fresh()->assigned_driver_id)->toBe($driver->id)
        ->and($driver->fresh()->availability_status)->toBe(DriverAvailabilityStatus::Busy);
});

test('driver cannot accept order from different branch while busy', function () {
    ['business' => $business, 'branch' => $branchA] = seedDispatchBusiness();
    $branchB = BusinessBranch::factory()->for($business)->create();
    ['user' => $user, 'driver' => $driver] = seedPlatformDriver();

    app(AcceptDeliveryOrder::class)->handle(seedDispatchOrder($branchA), $driver, $user);

    expect(fn () => app(AcceptDeliveryOrder::class)->handle(
        seedDispatchOrder($branchB),
        $driver->fresh(),
        $user,
    ))->toThrow(ValidationException::class);
});

test('driver cannot exceed active order limit', function () {
    config(['business.dispatch.max_active_orders_per_driver' => 2]);

    ['branch' => $branch] = seedDispatchBusiness();
    ['user' => $user, 'driver' => $driver] = seedPlatformDriver();

    app(AcceptDeliveryOrder::class)->handle(seedDispatchOrder($branch), $driver, $user);
    app(AcceptDeliveryOrder::class)->handle(seedDispatchOrder($branch), $driver->fresh(), $user);

    expect(fn () => app(AcceptDeliveryOrder::class)->handle(
        seedDispatchOrder($branch),
        $driver->fresh(),
        $user,
    ))->toThrow(ValidationException::class);
});

test('only one driver can accept the same order', function () {
    ['branch' => $branch] = seedDispatchBusiness();
    ['user' => $userA, 'driver' => $driverA] = seedPlatformDriver();
    ['user' => $userB, 'driver' => $driverB] = seedPlatformDriver();
    $order = seedDispatchOrder($branch);

    app(AcceptDeliveryOrder::class)->handle($order, $driverA, $userA);

    expect(fn () => app(AcceptDeliveryOrder::class)->handle($order->fresh(), $driverB, $userB))
        ->toThrow(ValidationException::class)
        ->and($order->fresh()->assigned_driver_id)->toBe($driverA->id)
        ->and(DriverAssignment::query()->where('order_id', $order->id)->where('status', DriverAssignmentStatus::Accepted)->count())
        ->toBe(1);
});

test('rejected offer is hidden from the same driver', function () {
    ['branch' => $branch] = seedDispatchBusiness();
    ['driver' => $driver] = seedPlatformDriver();
    $order = seedDispatchOrder($branch);

    app(RejectDeliveryOffer::class)->handle($order, $driver);

    expect(app(AvailableOrdersQuery::class)->forDriver($driver->fresh()))->toHaveCount(0);
});

test('driver cannot pickup another drivers order', function () {
    ['branch' => $branch] = seedDispatchBusiness();
    ['user' => $userA, 'driver' => $driverA] = seedPlatformDriver();
    ['user' => $userB, 'driver' => $driverB] = seedPlatformDriver();
    $order = seedDispatchOrder($branch);

    app(AcceptDeliveryOrder::class)->handle($order, $driverA, $userA);
    app(MarkDriverArrived::class)->handle($order->fresh(), $driverA, $userA);
    app(MarkOrderReady::class)->handle($order->fresh(), $userA);

    expect(fn () => app(PickupOrder::class)->handle($order->fresh(), $driverB, $userB))
        ->toThrow(ValidationException::class);
});

test('driver cannot deliver another drivers order', function () {
    ['branch' => $branch] = seedDispatchBusiness();
    ['user' => $userA, 'driver' => $driverA] = seedPlatformDriver();
    ['user' => $userB, 'driver' => $driverB] = seedPlatformDriver();
    $order = seedDispatchOrder($branch);

    app(AcceptDeliveryOrder::class)->handle($order, $driverA, $userA);
    $order->forceFill([
        'order_status' => OrderStatus::OnTheWay,
        'driver_arrived_at' => now(),
        'ready_at' => now(),
        'picked_up_at' => now(),
    ])->save();

    expect(fn () => app(DeliverOrder::class)->handle($order->fresh(), $driverB, $userB))
        ->toThrow(ValidationException::class);
});

test('first accepted order creates trip and second joins same trip', function () {
    ['branch' => $branch] = seedDispatchBusiness();
    ['user' => $user, 'driver' => $driver] = seedPlatformDriver();
    $first = seedDispatchOrder($branch);
    $second = seedDispatchOrder($branch);

    app(AcceptDeliveryOrder::class)->handle($first, $driver, $user);
    app(AcceptDeliveryOrder::class)->handle($second, $driver->fresh(), $user);

    $trips = DeliveryTrip::query()->where('driver_id', $driver->id)->get();
    $tripOrders = DeliveryTripOrder::query()
        ->whereIn('order_id', [$first->id, $second->id])
        ->pluck('delivery_trip_id')
        ->unique();

    expect($trips)->toHaveCount(1)
        ->and($tripOrders)->toHaveCount(1)
        ->and($trips->first()?->status)->toBe(DeliveryTripStatus::InProgress);
});

test('trip completes when all orders are delivered', function () {
    ['branch' => $branch] = seedDispatchBusiness();
    ['user' => $user, 'driver' => $driver] = seedPlatformDriver();
    $order = seedDispatchOrder($branch);

    app(AcceptDeliveryOrder::class)->handle($order, $driver, $user);
    app(MarkDriverArrived::class)->handle($order->fresh(), $driver, $user);
    app(MarkOrderReady::class)->handle($order->fresh(), $user);
    app(PickupOrder::class)->handle($order->fresh(), $driver, $user);
    app(StartDelivery::class)->handle($order->fresh(), $driver, $user);
    app(DeliverOrder::class)->handle($order->fresh(), $driver, $user);

    $trip = DeliveryTrip::query()->where('driver_id', $driver->id)->first();

    expect($order->fresh()->order_status)->toBe(OrderStatus::Delivered)
        ->and($trip?->status)->toBe(DeliveryTripStatus::Completed)
        ->and($driver->fresh()->availability_status)->toBe(DriverAvailabilityStatus::Available);
});

test('driver http accept assigns order safely', function () {
    ['branch' => $branch] = seedDispatchBusiness();
    ['user' => $user, 'driver' => $driver] = seedPlatformDriver();
    $order = seedDispatchOrder($branch);

    $this->actingAs($user)
        ->post(route('driver.orders.accept', $order))
        ->assertRedirect(route('driver.home'));

    expect($order->fresh()->assigned_driver_id)->toBe($driver->id)
        ->and($order->fresh()->order_status)->toBe(OrderStatus::DriverAssigned);
});

test('second http accept fails cleanly for taken order', function () {
    ['branch' => $branch] = seedDispatchBusiness();
    ['user' => $userA, 'driver' => $driverA] = seedPlatformDriver();
    ['user' => $userB] = seedPlatformDriver();
    $order = seedDispatchOrder($branch);

    app(AcceptDeliveryOrder::class)->handle($order, $driverA, $userA);

    $this->actingAs($userB)
        ->from(route('driver.orders.index'))
        ->post(route('driver.orders.accept', $order))
        ->assertRedirect(route('driver.orders.index'))
        ->assertSessionHasErrors('order');
});

test('business accept then driver full happy path', function () {
    ['branch' => $branch] = seedDispatchBusiness();
    ['user' => $driverUser, 'driver' => $driver] = seedPlatformDriver();
    $businessUser = User::factory()->businessAdmin()->create();
    $order = seedDispatchOrder($branch, OrderStatus::PendingBusiness);

    app(AcceptBusinessOrder::class)->handle($order, $businessUser, 15);
    expect($order->fresh()->order_status)->toBe(OrderStatus::Preparing);

    app(AcceptDeliveryOrder::class)->handle($order->fresh(), $driver, $driverUser);
    app(MarkDriverArrived::class)->handle($order->fresh(), $driver, $driverUser);
    app(MarkOrderReady::class)->handle($order->fresh(), $businessUser);
    app(PickupOrder::class)->handle($order->fresh(), $driver, $driverUser);
    app(StartDelivery::class)->handle($order->fresh(), $driver, $driverUser);
    app(DeliverOrder::class)->handle($order->fresh(), $driver, $driverUser);

    expect($order->fresh()->order_status)->toBe(OrderStatus::Delivered);
});
