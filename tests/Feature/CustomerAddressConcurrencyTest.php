<?php

use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\User;
use App\Services\Customers\CustomerAddressService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

function addressPayload(string $label, bool $isDefault = false): array
{
    return [
        'label' => $label,
        'address_text' => $label.' street',
        'formatted_address' => $label.' street, Comitán',
        'reference' => null,
        'latitude' => 16.2514,
        'longitude' => -92.1342,
        'place_id' => 'place-'.$label,
        'google_maps_url' => null,
        'is_default' => $isDefault,
    ];
}

test('customer cannot exceed max active addresses under locked create', function () {
    $user = User::factory()->customer()->create();
    $customer = Customer::factory()->forUser($user)->create();
    $service = app(CustomerAddressService::class);

    for ($i = 1; $i <= 4; $i++) {
        $service->create($customer, addressPayload("A{$i}", $i === 1));
    }

    expect(fn () => $service->create($customer, addressPayload('A5')))
        ->toThrow(ValidationException::class);

    expect($customer->addresses()->where('is_active', true)->count())->toBe(4);
});

test('only one active default address is allowed', function () {
    $user = User::factory()->customer()->create();
    $customer = Customer::factory()->forUser($user)->create();
    $service = app(CustomerAddressService::class);

    $first = $service->create($customer, addressPayload('Casa', true));
    $second = $service->create($customer, addressPayload('Trabajo', true));

    expect($first->fresh()->is_default)->toBeFalse()
        ->and($second->fresh()->is_default)->toBeTrue()
        ->and($customer->addresses()->where('is_active', true)->where('is_default', true)->count())->toBe(1);
});

test('simultaneous conceptual creates respect max addresses via lockForUpdate', function () {
    $user = User::factory()->customer()->create();
    $customer = Customer::factory()->forUser($user)->create();
    $service = app(CustomerAddressService::class);

    CustomerAddress::factory()->count(3)->create([
        'customer_id' => $customer->id,
        'is_active' => true,
        'is_default' => false,
    ]);
    CustomerAddress::factory()->create([
        'customer_id' => $customer->id,
        'is_active' => true,
        'is_default' => true,
    ]);

    $errors = 0;
    $created = 0;

    // Two sequential calls after capacity is full — second path mirrors a lost race after count check.
    foreach ([1, 2] as $i) {
        try {
            $service->create($customer, addressPayload("Race{$i}"));
            $created++;
        } catch (ValidationException) {
            $errors++;
        }
    }

    expect($created)->toBe(0)
        ->and($errors)->toBe(2)
        ->and($customer->addresses()->where('is_active', true)->count())->toBe(4);
});

test('simultaneous default changes leave a single default', function () {
    $user = User::factory()->customer()->create();
    $customer = Customer::factory()->forUser($user)->create();
    $service = app(CustomerAddressService::class);

    $a = $service->create($customer, addressPayload('A', true));
    $b = $service->create($customer, addressPayload('B', false));
    $c = $service->create($customer, addressPayload('C', false));

    $service->makeDefault($b);
    $service->makeDefault($c);

    expect($customer->addresses()->where('is_active', true)->where('is_default', true)->count())->toBe(1)
        ->and($c->fresh()->is_default)->toBeTrue()
        ->and($b->fresh()->is_default)->toBeFalse()
        ->and($a->fresh()->is_default)->toBeFalse();
});

test('deleting the default promotes another active address', function () {
    $user = User::factory()->customer()->create();
    $customer = Customer::factory()->forUser($user)->create();
    $service = app(CustomerAddressService::class);

    $default = $service->create($customer, addressPayload('Default', true));
    $other = $service->create($customer, addressPayload('Other', false));

    $service->delete($default);

    expect($default->fresh()->trashed())->toBeTrue()
        ->and($other->fresh()->is_default)->toBeTrue()
        ->and($customer->addresses()->where('is_active', true)->where('is_default', true)->count())->toBe(1);
});

test('deleting the only address leaves no default', function () {
    $user = User::factory()->customer()->create();
    $customer = Customer::factory()->forUser($user)->create();
    $service = app(CustomerAddressService::class);

    $only = $service->create($customer, addressPayload('Only', true));
    $service->delete($only);

    expect($customer->addresses()->where('is_active', true)->count())->toBe(0)
        ->and($customer->addresses()->withTrashed()->where('is_default', true)->count())->toBe(0);
});

test('unique default_customer_slot rejects a second active default at the database', function () {
    $user = User::factory()->customer()->create();
    $customer = Customer::factory()->forUser($user)->create();

    CustomerAddress::factory()->create([
        'customer_id' => $customer->id,
        'is_active' => true,
        'is_default' => true,
    ]);

    expect(function () use ($customer): void {
        DB::table('customer_addresses')->insert([
            'customer_id' => $customer->id,
            'label' => 'Hack',
            'address_text' => 'Hack',
            'latitude' => 16.25,
            'longitude' => -92.13,
            'is_default' => true,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    })->toThrow(UniqueConstraintViolationException::class);
});

test('http store still enforces max addresses', function () {
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
});
