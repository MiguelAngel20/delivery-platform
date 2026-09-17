<?php

use App\Models\User;

test('customer login screen can be rendered', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('auth/login')
            ->where('portal', 'customer')
            ->where('description', 'Accede a tu cuenta'));
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

test('customer credentials are rejected on the admin login portal', function () {
    $customer = User::factory()->create();

    $this->get(route('admin.login'))->assertOk();

    $this->from(route('admin.login'))
        ->post(route('login.store'), [
            'email' => $customer->email,
            'password' => 'password',
        ])
        ->assertSessionHasErrors('email')
        ->assertRedirect(route('admin.login'));

    $this->assertGuest();
});

test('admin can authenticate through the admin login portal', function () {
    $admin = User::factory()->systemAdmin()->create();

    $this->get(route('admin.login'))->assertOk();

    $this->post(route('login.store'), [
        'email' => $admin->email,
        'password' => 'password',
    ])->assertRedirect(route('admin.home', absolute: false));

    $this->assertAuthenticatedAs($admin);
});
