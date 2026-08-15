<?php

use App\Models\Driver;
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

test('driver home includes available orders for realtime refresh', function () {
    $user = User::factory()->driver()->create();
    Driver::factory()->approved()->forUser($user)->create();

    $this->actingAs($user)
        ->get(route('driver.home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('driver/home')
            ->has('availableOrders')
            ->has('compatibleOrders')
            ->has('activeGroups')
            ->has('stats')
            ->has('availabilityStatus')
            ->has('realtime.driver_id'));
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
