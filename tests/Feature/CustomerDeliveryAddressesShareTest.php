<?php

use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\User;

test('guest storefront does not receive customer addresses', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('customerAddresses', []));
});

test('customer storefront receives saved addresses for delivery selection', function () {
    $user = User::factory()->customer()->create();
    $customer = Customer::factory()->forUser($user)->create();

    CustomerAddress::factory()->create([
        'customer_id' => $customer->id,
        'label' => 'Casa',
        'address_text' => 'Calle Principal 12',
        'latitude' => '16.2514',
        'longitude' => '-92.1342',
        'is_default' => true,
        'is_active' => true,
    ]);

    CustomerAddress::factory()->create([
        'customer_id' => $customer->id,
        'label' => 'Trabajo',
        'address_text' => 'Av. Central 40',
        'latitude' => '16.2600',
        'longitude' => '-92.1400',
        'is_default' => false,
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('customerAddresses', 2)
            ->where('customerAddresses.0.label', 'Casa')
            ->where('customerAddresses.0.isDefault', true)
            ->where('customerAddresses.1.label', 'Trabajo')
            ->where('customerAddresses.1.isDefault', false));
});

test('inactive customer addresses are not shared', function () {
    $user = User::factory()->customer()->create();
    $customer = Customer::factory()->forUser($user)->create();

    CustomerAddress::factory()->create([
        'customer_id' => $customer->id,
        'label' => 'Activa',
        'is_active' => true,
    ]);

    CustomerAddress::factory()->create([
        'customer_id' => $customer->id,
        'label' => 'Inactiva',
        'is_active' => false,
    ]);

    $this->actingAs($user)
        ->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('customerAddresses', 1)
            ->where('customerAddresses.0.label', 'Activa'));
});
