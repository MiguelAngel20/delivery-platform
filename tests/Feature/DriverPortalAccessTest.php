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

test('business user cannot open driver portal', function () {
    $user = User::factory()->businessAdmin()->create();

    $this->actingAs($user)
        ->get(route('driver.home'))
        ->assertForbidden();
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
