<?php

use App\Enums\BusinessOperationMode;
use App\Enums\BusinessStatus;
use App\Enums\OrderAddressSource;
use App\Enums\OrderAddressType;
use App\Enums\OrderStatus;
use App\Models\Business;
use App\Models\BusinessBranch;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\OrderAddress;
use App\Models\User;

test('customer can save valid address', function () {
    $user = User::factory()->customer()->create();
    $customer = Customer::factory()->forUser($user)->create();

    $this->actingAs($user)
        ->post(route('customer.addresses.store'), [
            'label' => 'Casa',
            'address_text' => '5a Avenida Sur 12',
            'formatted_address' => '5a Avenida Sur 12, Comitán',
            'reference' => 'Portón negro',
            'latitude' => 16.2514,
            'longitude' => -92.1342,
            'place_id' => 'abc123',
            'is_default' => true,
        ])
        ->assertRedirect();

    expect($customer->addresses()->count())->toBe(1)
        ->and($customer->addresses()->first()->place_id)->toBe('abc123');
});

test('customer cannot exceed four active addresses', function () {
    $user = User::factory()->customer()->create();
    $customer = Customer::factory()->forUser($user)->create();

    CustomerAddress::factory()->count(4)->create([
        'customer_id' => $customer->id,
        'is_active' => true,
        'is_default' => false,
    ]);

    $this->actingAs($user)
        ->post(route('customer.addresses.store'), [
            'label' => 'Extra',
            'address_text' => 'Otra',
            'latitude' => 16.25,
            'longitude' => -92.13,
        ])
        ->assertSessionHasErrors();

    expect($customer->addresses()->where('is_active', true)->count())->toBe(4);
});

test('temporary address does not create saved address', function () {
    $user = User::factory()->customer()->create();
    $customer = Customer::factory()->forUser($user)->create();

    $before = $customer->addresses()->count();

    expect($before)->toBe(0);
});

test('order keeps address snapshot after customer edits saved address', function () {
    $user = User::factory()->customer()->create();
    $customer = Customer::factory()->forUser($user)->create();
    $address = CustomerAddress::factory()->create([
        'customer_id' => $customer->id,
        'address_text' => 'Original 1',
        'latitude' => 16.2514,
        'longitude' => -92.1342,
        'is_active' => true,
    ]);

    $business = Business::factory()->create([
        'status' => BusinessStatus::Active,
        'operation_mode' => BusinessOperationMode::Partner,
    ]);
    $branch = BusinessBranch::factory()->for($business)->create();

    $order = Order::factory()->create([
        'customer_id' => $customer->id,
        'branch_id' => $branch->id,
        'order_status' => OrderStatus::PendingBusiness,
    ]);

    OrderAddress::factory()->create([
        'order_id' => $order->id,
        'type' => OrderAddressType::Delivery,
        'source' => OrderAddressSource::SavedAddress,
        'address_text' => $address->address_text,
        'latitude' => $address->latitude,
        'longitude' => $address->longitude,
    ]);

    $address->update([
        'address_text' => 'Editada 99',
        'latitude' => 16.30,
        'longitude' => -92.20,
    ]);

    $snapshot = $order->fresh()->deliveryAddress;

    expect($snapshot?->address_text)->toBe('Original 1')
        ->and((float) $snapshot->latitude)->toBe(16.2514);
});
