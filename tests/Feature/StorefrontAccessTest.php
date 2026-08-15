<?php

use App\Models\User;

test('guests can browse the public storefront', function (string $routeName) {
    $this->get(route($routeName))->assertOk();
})->with([
    'home' => 'home',
    'restaurants' => 'restaurants.index',
    'categories' => 'categories.index',
    'promotions' => 'promotions.index',
    'search' => 'search',
    'cart' => 'cart',
]);

test('guests can open a restaurant menu', function () {
    $this->get(route('restaurants.show', ['slug' => 'pollo-guero']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('public/restaurants/show')
            ->where('slug', 'pollo-guero'));
});

test('customer can open customer portal pages', function (string $routeName) {
    $user = User::factory()->customer()->create();

    $this->actingAs($user)
        ->get(route($routeName))
        ->assertOk();
})->with([
    'checkout' => 'customer.checkout',
    'orders' => 'customer.orders.index',
    'addresses' => 'customer.addresses.index',
    'profile' => 'customer.profile.index',
]);

test('customer can logout from the storefront', function () {
    $user = User::factory()->customer()->create();

    $this->actingAs($user)
        ->post(route('logout'))
        ->assertRedirect(route('home'));

    $this->assertGuest();
});

test('customer can open order tracking', function () {
    $user = User::factory()->customer()->create();

    $this->actingAs($user)
        ->get(route('customer.orders.show', ['order' => 'ord-1']))
        ->assertOk();
});

test('driver cannot open customer checkout', function () {
    $user = User::factory()->driver()->create();

    $this->actingAs($user)
        ->get(route('customer.checkout'))
        ->assertForbidden();
});

test('guests are redirected from customer pages', function () {
    $this->get(route('customer.orders.index'))
        ->assertRedirect(route('login'));
});
