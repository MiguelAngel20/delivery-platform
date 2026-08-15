<?php

test('customer login screen can be rendered', function () {
    $this->get(route('login'))->assertOk();
});

test('admin login screen can be rendered', function () {
    $this->get(route('admin.login'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('auth/login')
            ->where('portal', 'admin')
            ->where('title', 'Acceso administración'));
});

test('business login screen can be rendered', function () {
    $this->get(route('business.login'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('auth/login')
            ->where('portal', 'business'));
});

test('driver login screen can be rendered', function () {
    $this->get(route('driver.login'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('auth/login')
            ->where('portal', 'driver'));
});
