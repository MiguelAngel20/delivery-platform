<?php

use App\Enums\CustomerTrustLevel;
use App\Enums\CustomOrderRequestStatus;
use App\Enums\FinancialPartyType;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\CustomOrderRequest;
use App\Models\Driver;
use App\Models\Order;
use App\Models\User;
use App\Services\Finance\OrderFinancialService;
use App\Services\Orders\CustomOrderRequestService;
use App\Services\Orders\OrderQuoteService;
use Illuminate\Validation\ValidationException;

function seedCustomCustomer(): array
{
    $user = User::factory()->customer()->create();
    $customer = Customer::factory()->for($user)->create();
    $address = CustomerAddress::factory()->create([
        'customer_id' => $customer->id,
        'is_default' => true,
    ]);

    return compact('user', 'customer', 'address');
}

function customRequestPayload(CustomerAddress $address): array
{
    return [
        'establishment_name' => 'Cafetería Central',
        'description' => '2 frappés grandes de moka y 1 crepa de Nutella',
        'customer_notes' => 'Sin crema batida',
        'merchant_address' => 'Av. Reforma 10',
        'merchant_latitude' => 16.2514,
        'merchant_longitude' => -92.1342,
        'merchant_formatted_address' => 'Av. Reforma 10, Comitán',
        'merchant_reference' => 'Local esquina',
        'delivery' => [
            'source' => 'saved_address',
            'customer_address_id' => $address->id,
        ],
    ];
}

function quoteItems(): array
{
    return [
        [
            'description' => 'Frappé de moka',
            'quantity' => 2,
            'unit_price' => 60,
            'acquisition_cost' => 50,
        ],
        [
            'description' => 'Crepa de Nutella',
            'quantity' => 1,
            'unit_price' => 70,
            'acquisition_cost' => 60,
        ],
    ];
}

test('customer can create custom request', function () {
    ['user' => $user, 'address' => $address] = seedCustomCustomer();

    $this->actingAs($user)
        ->post(route('customer.custom-orders.store'), customRequestPayload($address))
        ->assertRedirect();

    $request = CustomOrderRequest::query()->first();

    expect($request)->not->toBeNull()
        ->and($request?->status)->toBe(CustomOrderRequestStatus::PendingReview)
        ->and($request?->establishment_name)->toBe('Cafetería Central');
});

test('customer cannot access another customers request', function () {
    ['user' => $owner, 'address' => $address] = seedCustomCustomer();
    $other = User::factory()->customer()->create();
    Customer::factory()->for($other)->create();

    $this->actingAs($owner)
        ->post(route('customer.custom-orders.store'), customRequestPayload($address));

    $request = CustomOrderRequest::query()->firstOrFail();

    $this->actingAs($other)
        ->get(route('customer.custom-orders.show', $request))
        ->assertNotFound();
});

test('blocked customer cannot create custom request', function () {
    ['user' => $user, 'customer' => $customer, 'address' => $address] = seedCustomCustomer();
    $customer->forceFill(['trust_level' => CustomerTrustLevel::Blocked])->save();
    $user->refresh();

    $this->actingAs($user)
        ->post(route('customer.custom-orders.store'), customRequestPayload($address))
        ->assertForbidden();
});

test('admin can claim request and second admin cannot take it', function () {
    ['user' => $user, 'customer' => $customer, 'address' => $address] = seedCustomCustomer();
    $adminA = User::factory()->systemAdmin()->create();
    $adminB = User::factory()->systemAdmin()->create();

    $request = app(CustomOrderRequestService::class)->create(
        $customer,
        $user,
        customRequestPayload($address),
    );

    $this->actingAs($adminA)
        ->from(route('admin.custom-orders.show', $request))
        ->post(route('admin.custom-orders.claim', $request))
        ->assertRedirect();

    expect($request->fresh()->assigned_admin_user_id)->toBe($adminA->id)
        ->and($request->fresh()->status)->toBe(CustomOrderRequestStatus::Reviewing);

    $this->actingAs($adminB)
        ->from(route('admin.custom-orders.show', $request))
        ->post(route('admin.custom-orders.claim', $request->fresh()))
        ->assertRedirect()
        ->assertSessionHasErrors('request');
});

test('admin can create quote and only owner can accept', function () {
    ['user' => $user, 'customer' => $customer, 'address' => $address] = seedCustomCustomer();
    $other = User::factory()->customer()->create();
    Customer::factory()->for($other)->create();
    $admin = User::factory()->systemAdmin()->create();

    $request = app(CustomOrderRequestService::class)->create(
        $customer,
        $user,
        customRequestPayload($address),
    );
    app(CustomOrderRequestService::class)->claim($request, $admin);

    $this->actingAs($admin)
        ->post(route('admin.custom-orders.quote', $request), [
            'service_fee' => 50,
            'items' => quoteItems(),
        ])
        ->assertRedirect();

    expect($request->fresh()->status)->toBe(CustomOrderRequestStatus::Quoted)
        ->and($request->fresh()->latestQuote()?->total)->toBe('240.00');

    $this->actingAs($other)
        ->post(route('customer.custom-orders.accept', $request->fresh()))
        ->assertNotFound();

    $this->actingAs($user)
        ->post(route('customer.custom-orders.accept', $request->fresh()))
        ->assertRedirect();
});

test('accepted quote creates exactly one order', function () {
    ['user' => $user, 'customer' => $customer, 'address' => $address] = seedCustomCustomer();
    $admin = User::factory()->systemAdmin()->create();

    $request = app(CustomOrderRequestService::class)->create(
        $customer,
        $user,
        customRequestPayload($address),
    );
    app(CustomOrderRequestService::class)->claim($request, $admin);
    app(OrderQuoteService::class)->createCustomQuote($request, $admin, quoteItems(), '50');

    $order = app(OrderQuoteService::class)->acceptCustomQuote($request->fresh(), $user);

    expect(Order::query()->count())->toBe(1)
        ->and($order->type)->toBe(OrderType::Custom)
        ->and($order->order_status)->toBe(OrderStatus::PendingPlatform)
        ->and($order->items)->toHaveCount(2)
        ->and($order->items->pluck('product_name')->all())->toBe([
            'Frappé de moka',
            'Crepa de Nutella',
        ])
        ->and((string) $order->total)->toBe('240.00')
        ->and((string) $order->financial?->business_amount)->toBe('160.00')
        ->and((string) $order->financial?->customer_total)->toBe('240.00')
        ->and($order->merchant_name_snapshot)->toBe('Cafetería Central');
});

test('duplicate accept request does not create duplicate order', function () {
    ['user' => $user, 'customer' => $customer, 'address' => $address] = seedCustomCustomer();
    $admin = User::factory()->systemAdmin()->create();

    $request = app(CustomOrderRequestService::class)->create(
        $customer,
        $user,
        customRequestPayload($address),
    );
    app(CustomOrderRequestService::class)->claim($request, $admin);
    app(OrderQuoteService::class)->createCustomQuote($request, $admin, quoteItems(), '50');

    $this->actingAs($user)
        ->post(route('customer.custom-orders.accept', $request));

    $this->actingAs($user)
        ->post(route('customer.custom-orders.accept', $request->fresh()))
        ->assertRedirect();

    expect(Order::query()->count())->toBe(1)
        ->and($request->fresh()->quoted_order_id)->not->toBeNull()
        ->and($request->fresh()->status)->toBe(CustomOrderRequestStatus::ConvertedToOrder);
});

test('converted custom order pickup uses external merchant party', function () {
    ['user' => $user, 'customer' => $customer, 'address' => $address] = seedCustomCustomer();
    $admin = User::factory()->systemAdmin()->create();

    $request = app(CustomOrderRequestService::class)->create(
        $customer,
        $user,
        customRequestPayload($address),
    );
    app(CustomOrderRequestService::class)->claim($request, $admin);
    app(OrderQuoteService::class)->createCustomQuote($request, $admin, quoteItems(), '50');
    $order = app(OrderQuoteService::class)->acceptCustomQuote($request->fresh(), $user);

    $financials = app(OrderFinancialService::class);
    $driverUser = User::factory()->driver()->create();
    $driver = Driver::factory()->approved()->forUser($driverUser)->create();

    $order->forceFill([
        'order_status' => OrderStatus::Preparing,
        'assigned_driver_id' => $driver->id,
        'picked_up_at' => now(),
    ])->save();

    $tx = $financials->recordPickupPayment($order->fresh(['branch.business', 'financial', 'items']), $driver);

    expect($tx)->not->toBeNull()
        ->and($tx?->to_party_type)->toBe(FinancialPartyType::ExternalMerchant)
        ->and($tx?->to_party_id)->toBeNull()
        ->and((string) $tx?->amount)->toBe('160.00');
});

test('customer cannot exceed max active custom requests', function () {
    ['user' => $user, 'customer' => $customer, 'address' => $address] = seedCustomCustomer();
    $service = app(CustomOrderRequestService::class);

    $service->create($customer, $user, customRequestPayload($address));
    $service->create($customer, $user, customRequestPayload($address));

    expect(fn () => $service->create($customer, $user, customRequestPayload($address)))
        ->toThrow(ValidationException::class);
});
