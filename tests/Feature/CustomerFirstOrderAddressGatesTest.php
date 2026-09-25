<?php

use App\Actions\Orders\CreateOrder;
use App\Enums\OrderAddressSource;
use App\Enums\OrderStatus;
use App\Models\BusinessBranch;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Validation\ValidationException;

test('registration requires a delivery reference', function () {
    $this->post(route('register.store'), [
        'first_name' => 'Ana',
        'last_name' => 'López',
        'email' => 'ana.ref@example.com',
        'phone_dial_code' => '+52',
        'phone_national' => '9611234568',
        'password' => 'Clave123!',
        'password_confirmation' => 'Clave123!',
        'address_label' => 'Casa',
        'address_text' => 'Calle Central 12, Comitán',
        'formatted_address' => 'Calle Central 12, Comitán',
        'reference' => '',
        'latitude' => 16.2512,
        'longitude' => -92.1342,
    ])->assertSessionHasErrors(['reference']);
});

test('checkout hides temporary address option until first delivered order', function () {
    $user = User::factory()->customer()->create();
    $customer = Customer::factory()->forUser($user)->create();
    CustomerAddress::factory()->create([
        'customer_id' => $customer->id,
        'is_default' => true,
        'is_active' => true,
        'label' => 'Casa',
    ]);

    $this->actingAs($user)
        ->get(route('customer.checkout'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('customer/checkout/index')
            ->where('hasCompletedOrder', false)
            ->where('canUseTemporaryAddress', false)
            ->has('addresses', 1)
            ->where('addresses.0.label', 'Casa'));
});

test('checkout allows temporary address after a delivered order', function () {
    $user = User::factory()->customer()->create();
    $customer = Customer::factory()->forUser($user)->create();
    CustomerAddress::factory()->create([
        'customer_id' => $customer->id,
        'is_default' => true,
        'is_active' => true,
    ]);
    Order::factory()->create([
        'customer_id' => $customer->id,
        'order_status' => OrderStatus::Delivered,
        'delivered_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('customer.checkout'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('hasCompletedOrder', true)
            ->where('canUseTemporaryAddress', true));
});

test('new customers cannot create extra addresses before a delivered order', function () {
    $user = User::factory()->customer()->create();
    $customer = Customer::factory()->forUser($user)->create();
    CustomerAddress::factory()->create([
        'customer_id' => $customer->id,
        'is_default' => true,
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->get(route('customer.addresses.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('hasCompletedOrder', false)
            ->where('canManageAddresses', false));

    $this->actingAs($user)
        ->post(route('customer.addresses.store'), [
            'label' => 'Trabajo',
            'address_text' => 'Otra calle',
            'latitude' => 16.2514,
            'longitude' => -92.1342,
            'is_default' => false,
        ])
        ->assertSessionHasErrors(['address']);

    expect($customer->addresses()->where('is_active', true)->count())->toBe(1);
});

test('new customers cannot delete their registration address before a delivered order', function () {
    $user = User::factory()->customer()->create();
    $customer = Customer::factory()->forUser($user)->create();
    $address = CustomerAddress::factory()->create([
        'customer_id' => $customer->id,
        'is_default' => true,
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->delete(route('customer.addresses.destroy', $address))
        ->assertSessionHasErrors(['address']);

    expect($address->fresh()->trashed())->toBeFalse()
        ->and($address->fresh()->is_active)->toBeTrue();
});

test('create order rejects temporary delivery before first delivered order', function () {
    $user = User::factory()->customer()->create();
    $customer = Customer::factory()->forUser($user)->create();
    CustomerAddress::factory()->create([
        'customer_id' => $customer->id,
        'is_default' => true,
        'is_active' => true,
        'latitude' => 16.2514,
        'longitude' => -92.1342,
    ]);

    $branch = BusinessBranch::factory()->create();
    $product = Product::factory()->create(['branch_id' => $branch->id]);

    expect(fn () => app(CreateOrder::class)->handle($customer, $user, [
        'branch_id' => $branch->id,
        'notes' => null,
        'items' => [
            [
                'product_id' => $product->id,
                'quantity' => 1,
                'selected_options' => [],
            ],
        ],
        'delivery' => [
            'source' => OrderAddressSource::Temporary->value,
            'address_text' => 'Temporal',
            'latitude' => 16.2514,
            'longitude' => -92.1342,
        ],
    ]))->toThrow(ValidationException::class);
});
