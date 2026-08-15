<?php

use Tests\TestCase;

uses(TestCase::class);

test('the public home page is accessible', function () {
    $this->get(route('home'))->assertOk();
});

test('the api v1 health endpoint is available', function () {
    $this->getJson(route('api.v1.health'))
        ->assertSuccessful()
        ->assertJson([
            'ok' => true,
            'version' => 'v1',
        ]);
});

test('guests are redirected from interface homes', function (string $routeName) {
    $this->get(route($routeName))->assertRedirect(route('login'));
})->with([
    'customer' => 'customer.home',
    'business' => 'business.home',
    'driver' => 'driver.home',
    'admin' => 'admin.home',
]);

test('guests are redirected from admin pages', function (string $routeName) {
    $this->get(route($routeName))->assertRedirect(route('login'));
})->with([
    'businesses' => 'admin.businesses.index',
    'drivers' => 'admin.drivers.index',
    'customers' => 'admin.customers.index',
    'orders' => 'admin.orders.index',
    'promotions' => 'admin.promotions.index',
    'reports' => 'admin.reports.index',
    'settings' => 'admin.settings.index',
]);

test('guests are redirected from business pages', function (string $routeName) {
    $this->get(route($routeName))->assertRedirect(route('login'));
})->with([
    'orders' => 'business.orders.index',
    'products' => 'business.products.index',
    'categories' => 'business.categories.index',
    'promotions' => 'business.promotions.index',
    'employees' => 'business.employees.index',
    'settings' => 'business.settings.index',
]);

test('guests are redirected from driver pages', function (string $routeName) {
    $this->get(route($routeName))->assertRedirect(route('login'));
})->with([
    'orders' => 'driver.orders.index',
    'earnings' => 'driver.earnings.index',
    'history' => 'driver.history.index',
    'profile' => 'driver.profile.index',
]);

test('guests are redirected from customer pages', function (string $routeName) {
    $this->get(route($routeName))->assertRedirect(route('login'));
})->with([
    'home' => 'customer.home',
    'checkout' => 'customer.checkout',
    'orders' => 'customer.orders.index',
    'addresses' => 'customer.addresses.index',
    'profile' => 'customer.profile.index',
]);
