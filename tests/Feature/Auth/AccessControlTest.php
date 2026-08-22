<?php

use App\Enums\UserRole;
use App\Models\User;

test('system admin can access admin portal', function () {
    $user = User::factory()->systemAdmin()->create();

    $this->actingAs($user)
        ->get(route('admin.home'))
        ->assertOk();
});

test('system admin cannot access business portal', function () {
    $user = User::factory()->systemAdmin()->create();

    $this->actingAs($user)
        ->get(route('business.home'))
        ->assertForbidden();
});

test('driver cannot access admin portal', function () {
    $user = User::factory()->driver()->create();

    $this->actingAs($user)
        ->get(route('admin.home'))
        ->assertForbidden();
});

test('business user cannot access admin portal', function () {
    $user = User::factory()->businessAdmin()->create();

    $this->actingAs($user)
        ->get(route('admin.home'))
        ->assertForbidden();
});

test('customer cannot access driver portal', function () {
    $user = User::factory()->customer()->create();

    $this->actingAs($user)
        ->get(route('driver.home'))
        ->assertForbidden();
});

test('business employee can access business portal', function () {
    $user = User::factory()->businessEmployee()->create();

    $this->actingAs($user)
        ->get(route('business.home'))
        ->assertOk();
});

test('login redirects each role to its home', function (UserRole $role, string $homeRoute) {
    $user = User::factory()->create(['role' => $role]);

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route($homeRoute));

    $this->assertAuthenticated();
})->with([
    'system admin' => [UserRole::SystemAdmin, 'admin.home'],
    'business admin' => [UserRole::BusinessAdmin, 'business.home'],
    'business employee' => [UserRole::BusinessEmployee, 'business.home'],
    'driver' => [UserRole::Driver, 'driver.home'],
    'customer' => [UserRole::Customer, 'customer.home'],
]);

test('suspended users cannot authenticate', function () {
    $user = User::factory()->customer()->suspended()->create();

    $this->from(route('login'))
        ->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors([
            'email' => 'Tu cuenta no está disponible actualmente.',
        ]);

    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->systemAdmin()->create();

    $this->actingAs($user)
        ->from(route('admin.home'))
        ->post(route('logout'))
        ->assertRedirect(route('home'));

    $this->assertGuest();
});
