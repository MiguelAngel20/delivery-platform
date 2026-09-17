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

test('admin session on the customer login page is cleared instead of redirecting home', function () {
    $admin = User::factory()->systemAdmin()->create();

    $this->actingAs($admin)
        ->get(route('login'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

test('inertia login visit with an admin session does not leave the storefront host', function () {
    $admin = User::factory()->systemAdmin()->create();

    $response = $this->actingAs($admin)
        ->withHeaders([
            'X-Inertia' => 'true',
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->get(route('login'));

    $this->assertGuest();

    $inertiaLocation = $response->headers->get('X-Inertia-Location');
    $redirectLocation = $response->headers->get('Location');

    expect($inertiaLocation ?? $redirectLocation)->not->toContain('admin.chisdrive.com');
});
