<?php

use App\Enums\DriverAvailabilityStatus;
use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Driver;
use App\Models\DriverRating;
use App\Models\Order;
use App\Models\User;

test('driver can open all driver portal pages', function (string $routeName) {
    $user = User::factory()->driver()->create();
    Driver::factory()->approved()->forUser($user)->create();

    $this->actingAs($user)
        ->get(route($routeName))
        ->assertOk();
})->with([
    'home' => 'driver.home',
    'orders' => 'driver.orders.index',
    'earnings' => 'driver.earnings.index',
    'history' => 'driver.history.index',
    'profile' => 'driver.profile.index',
]);

test('driver home includes completed orders and ratings for today by default', function () {
    $user = User::factory()->driver()->create();
    Driver::factory()->approved()->forUser($user)->create();

    $this->actingAs($user)
        ->get(route('driver.home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('driver/home')
            ->has('completedOrders')
            ->has('ratings')
            ->has('filters')
            ->where('filters.from', now()->toDateString())
            ->where('filters.to', now()->toDateString())
            ->has('realtime.driver_id'));
});

test('driver home lists delivered orders and ratings for selected date range', function () {
    $user = User::factory()->driver()->create();
    $driver = Driver::factory()->approved()->forUser($user)->create();
    $customerUser = User::factory()->customer()->create();
    $customer = Customer::factory()->forUser($customerUser)->create();

    $deliveredToday = Order::factory()->create([
        'customer_id' => $customer->id,
        'assigned_driver_id' => $driver->id,
        'order_status' => OrderStatus::Delivered,
        'delivered_at' => now(),
    ]);

    Order::factory()->create([
        'customer_id' => $customer->id,
        'assigned_driver_id' => $driver->id,
        'order_status' => OrderStatus::Delivered,
        'delivered_at' => now()->subDays(3),
    ]);

    DriverRating::factory()->create([
        'order_id' => $deliveredToday->id,
        'driver_id' => $driver->id,
        'customer_id' => $customer->id,
        'overall_rating' => 5,
        'comment' => 'Excelente servicio.',
        'created_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('driver.home', [
            'from' => now()->toDateString(),
            'to' => now()->toDateString(),
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('completedOrders', 1)
            ->where('completedOrders.0.id', $deliveredToday->id)
            ->has('ratings', 1)
            ->where('ratings.0.comment', 'Excelente servicio.'));
});

test('driver can view delivered order details', function () {
    $user = User::factory()->driver()->create();
    $driver = Driver::factory()->approved()->forUser($user)->create();
    $order = Order::factory()->create([
        'assigned_driver_id' => $driver->id,
        'order_status' => OrderStatus::Delivered,
        'delivered_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('driver.orders.show', $order))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('driver/orders/show')
            ->where('order.id', $order->id));
});

test('driver cannot view active order details page', function () {
    $user = User::factory()->driver()->create();
    $driver = Driver::factory()->approved()->forUser($user)->create();
    $order = Order::factory()->create([
        'assigned_driver_id' => $driver->id,
        'order_status' => OrderStatus::DriverAssigned,
    ]);

    $this->actingAs($user)
        ->get(route('driver.orders.show', $order))
        ->assertNotFound();
});

test('driver orders page shares realtime driver id', function () {
    $user = User::factory()->driver()->create();
    $driver = Driver::factory()->approved()->forUser($user)->create();

    $this->actingAs($user)
        ->get(route('driver.orders.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('driver/orders/index')
            ->has('availableOrders')
            ->has('activeOrders')
            ->where('realtime.driver_id', $driver->id));
});

test('driver portal shares availability status for header badge', function () {
    $user = User::factory()->driver()->create();
    Driver::factory()->approved()->forUser($user)->create([
        'availability_status' => DriverAvailabilityStatus::Offline,
    ]);

    $this->actingAs($user)
        ->get(route('driver.orders.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('driver.availabilityStatus', 'offline'));
});

test('driver cannot disconnect while having active assigned orders', function () {
    $user = User::factory()->driver()->create();
    $driver = Driver::factory()->approved()->forUser($user)->create([
        'availability_status' => DriverAvailabilityStatus::Busy,
    ]);

    Order::factory()->create([
        'assigned_driver_id' => $driver->id,
        'order_status' => OrderStatus::DriverAssigned,
    ]);

    $this->actingAs($user)
        ->from(route('driver.home'))
        ->patch(route('driver.availability.update'), [
            'availability_status' => 'offline',
        ])
        ->assertSessionHasErrors('availability_status');

    expect($driver->fresh()->availability_status)->toBe(DriverAvailabilityStatus::Busy);
});

test('driver can disconnect when no active assigned orders', function () {
    $user = User::factory()->driver()->create();
    $driver = Driver::factory()->approved()->forUser($user)->create([
        'availability_status' => DriverAvailabilityStatus::Available,
    ]);

    $this->actingAs($user)
        ->from(route('driver.home'))
        ->patch(route('driver.availability.update'), [
            'availability_status' => 'offline',
        ])
        ->assertRedirect(route('driver.home'));

    expect($driver->fresh()->availability_status)->toBe(DriverAvailabilityStatus::Offline);
});

test('driver portal shares active orders flag for disconnect guard', function () {
    $user = User::factory()->driver()->create();
    $driver = Driver::factory()->approved()->forUser($user)->create();

    Order::factory()->create([
        'assigned_driver_id' => $driver->id,
        'order_status' => OrderStatus::OnTheWay,
    ]);

    $this->actingAs($user)
        ->get(route('driver.home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('driver.hasActiveOrders', true));
});

test('driver can connect from offline via availability patch', function () {
    $user = User::factory()->driver()->create();
    $driver = Driver::factory()->approved()->forUser($user)->create([
        'availability_status' => DriverAvailabilityStatus::Offline,
    ]);

    $this->actingAs($user)
        ->from(route('driver.home'))
        ->patch(route('driver.availability.update'), [
            'availability_status' => 'available',
            'latitude' => 16.2525,
            'longitude' => -92.1376,
        ])
        ->assertRedirect(route('driver.home'));

    expect($driver->fresh()->availability_status)->toBe(DriverAvailabilityStatus::Available);
});

test('customer cannot open driver portal', function () {
    $user = User::factory()->customer()->create();

    $this->actingAs($user)
        ->get(route('driver.orders.index'))
        ->assertForbidden();
});

test('driver can logout', function () {
    $user = User::factory()->driver()->create();
    Driver::factory()->approved()->forUser($user)->create();

    $this->actingAs($user)
        ->post(route('logout'))
        ->assertRedirect(route('home'));

    $this->assertGuest();
});
