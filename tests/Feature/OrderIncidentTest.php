<?php

use App\Actions\Dispatch\AcceptDeliveryOrder;
use App\Actions\Orders\AcceptBusinessOrder;
use App\Actions\Orders\CreateOrder;
use App\Enums\BusinessOperationMode;
use App\Enums\BusinessStatus;
use App\Enums\BusinessUserRole;
use App\Enums\BusinessUserStatus;
use App\Enums\CancellationResponsibility;
use App\Enums\DriverAvailabilityStatus;
use App\Enums\DriverScope;
use App\Enums\IncidentStatus;
use App\Enums\IncidentType;
use App\Enums\OrderStatus;
use App\Models\Business;
use App\Models\BusinessBranch;
use App\Models\BusinessUser;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Driver;
use App\Models\Incident;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\User;

function incidentCatalog(): array
{
    $business = Business::factory()->create([
        'operation_mode' => BusinessOperationMode::Partner,
        'status' => BusinessStatus::Active,
    ]);
    $branch = BusinessBranch::factory()->for($business)->create();
    $product = Product::factory()->create([
        'branch_id' => $branch->id,
        'is_active' => true,
        'is_available' => true,
    ]);
    ProductPrice::factory()->create([
        'product_id' => $product->id,
        'list_price' => 80,
        'is_active' => true,
    ]);

    return compact('business', 'branch', 'product');
}

function incidentCustomer(): array
{
    $user = User::factory()->customer()->create();
    $customer = Customer::factory()->for($user)->create();
    $address = CustomerAddress::factory()->create([
        'customer_id' => $customer->id,
        'is_default' => true,
    ]);

    return compact('user', 'customer', 'address');
}

function incidentBusinessAdmin(Business $business): User
{
    $admin = User::factory()->businessAdmin()->create();
    BusinessUser::query()->create([
        'business_id' => $business->id,
        'user_id' => $admin->id,
        'role' => BusinessUserRole::BusinessAdmin,
        'status' => BusinessUserStatus::Active,
    ]);

    return $admin;
}

function createIncidentOrder(): array
{
    ['user' => $user, 'customer' => $customer, 'address' => $address] = incidentCustomer();
    ['business' => $business, 'branch' => $branch, 'product' => $product] = incidentCatalog();

    $order = app(CreateOrder::class)->handle($customer, $user, [
        'branch_id' => $branch->id,
        'items' => [
            [
                'product_id' => $product->id,
                'quantity' => 1,
                'selected_options' => [],
            ],
        ],
        'delivery' => [
            'source' => 'saved_address',
            'customer_address_id' => $address->id,
        ],
    ]);

    return compact('order', 'user', 'customer', 'business', 'branch');
}

test('driver can report incident on assigned order', function () {
    ['order' => $order, 'business' => $business] = createIncidentOrder();
    $admin = User::factory()->businessAdmin()->create();
    BusinessUser::query()->create([
        'business_id' => $business->id,
        'user_id' => $admin->id,
        'role' => BusinessUserRole::BusinessAdmin,
        'status' => BusinessUserStatus::Active,
    ]);
    app(AcceptBusinessOrder::class)->handle($order, $admin, 15);

    $driverUser = User::factory()->driver()->create();
    $driver = Driver::factory()->approved()->forUser($driverUser)->create([
        'driver_scope' => DriverScope::Platform,
        'availability_status' => DriverAvailabilityStatus::Available,
    ]);
    app(AcceptDeliveryOrder::class)->handle($order->fresh(), $driver, $driverUser);

    $this->actingAs($driverUser)
        ->post(route('driver.orders.incidents.store', $order->fresh()), [
            'type' => IncidentType::CustomerUnreachable->value,
            'description' => 'El cliente no contesta el teléfono.',
        ])
        ->assertRedirect();

    $incident = Incident::query()->where('order_id', $order->id)->first();

    expect($incident)->not->toBeNull()
        ->and($incident->type)->toBe(IncidentType::CustomerUnreachable)
        ->and($incident->reported_by_user_id)->toBe($driverUser->id)
        ->and($incident->driver_id)->toBe($driver->id)
        ->and($order->fresh()->order_status)->toBe(OrderStatus::DriverAssigned);
});

test('customer can report own order incident', function () {
    ['order' => $order, 'user' => $user] = createIncidentOrder();
    $order->forceFill([
        'order_status' => OrderStatus::PickedUp,
        'picked_up_at' => now(),
        'business_accepted_at' => now(),
    ])->save();

    $this->actingAs($user)
        ->post(route('customer.orders.incidents.store', $order->fresh()), [
            'type' => IncidentType::BusinessDelay->value,
            'description' => 'Lleva más tiempo del estimado.',
        ])
        ->assertRedirect();

    expect(Incident::query()->where('order_id', $order->id)->where('reported_by_user_id', $user->id)->exists())->toBeTrue()
        ->and($order->fresh()->order_status)->toBe(OrderStatus::PickedUp);
});

test('business employee can report incident for allowed branch', function () {
    ['order' => $order, 'business' => $business, 'branch' => $branch] = createIncidentOrder();
    $employee = User::factory()->businessEmployee()->create();
    $membership = BusinessUser::query()->create([
        'business_id' => $business->id,
        'user_id' => $employee->id,
        'role' => BusinessUserRole::BusinessEmployee,
        'status' => BusinessUserStatus::Active,
    ]);
    $membership->branches()->sync([$branch->id]);

    app(AcceptBusinessOrder::class)->handle(
        $order,
        incidentBusinessAdmin($business),
        10,
    );

    $this->actingAs($employee)
        ->post(route('business.orders.incidents.store', $order->fresh()), [
            'type' => IncidentType::CustomerUnreachable->value,
            'description' => 'Cliente no responde para confirmar el pedido.',
        ])
        ->assertRedirect();

    expect(Incident::query()->where('order_id', $order->id)->where('reported_by_user_id', $employee->id)->exists())->toBeTrue();
});

test('system admin can resolve incident', function () {
    ['order' => $order, 'user' => $user] = createIncidentOrder();
    $admin = incidentBusinessAdmin($order->branch->business);
    app(AcceptBusinessOrder::class)->handle($order, $admin, 15);

    $this->actingAs($user)
        ->post(route('customer.orders.incidents.store', $order->fresh()), [
            'type' => IncidentType::Other->value,
            'description' => 'Necesito ayuda con el pedido.',
        ]);

    $incident = Incident::query()->where('order_id', $order->id)->first();
    $systemAdmin = User::factory()->systemAdmin()->create();

    $this->actingAs($systemAdmin)
        ->post(route('admin.incidents.resolve', $incident), [
            'resolution' => 'Se contactó al cliente y el pedido continúa.',
        ])
        ->assertRedirect();

    $incident->refresh();

    expect($incident->status)->toBe(IncidentStatus::Resolved)
        ->and($incident->resolved_by_user_id)->toBe($systemAdmin->id)
        ->and($incident->resolution)->toBe('Se contactó al cliente y el pedido continúa.')
        ->and($incident->resolved_at)->not->toBeNull();
});

test('unauthorized user cannot resolve incident', function () {
    ['order' => $order, 'user' => $user] = createIncidentOrder();
    $admin = incidentBusinessAdmin($order->branch->business);
    app(AcceptBusinessOrder::class)->handle($order, $admin, 15);

    $this->actingAs($user)
        ->post(route('customer.orders.incidents.store', $order->fresh()), [
            'type' => IncidentType::Other->value,
            'description' => 'Problema reportado por el cliente.',
        ]);

    $incident = Incident::query()->where('order_id', $order->id)->first();

    $this->actingAs($user)
        ->post(route('admin.incidents.resolve', $incident), [
            'resolution' => 'Intento no autorizado.',
        ])
        ->assertForbidden();

    expect($incident->fresh()->status)->toBe(IncidentStatus::Open);
});

test('system admin can assign cancellation responsibility', function () {
    ['order' => $order] = createIncidentOrder();
    $admin = incidentBusinessAdmin($order->branch->business);
    app(AcceptBusinessOrder::class)->handle($order, $admin, 15);

    $this->actingAs($order->customer->user)
        ->post(route('customer.orders.cancel', $order->fresh()), [
            'reason_code' => 'customer_changed_mind',
        ])
        ->assertRedirect();

    $cancellation = $order->fresh()->cancellation;
    $systemAdmin = User::factory()->systemAdmin()->create();

    $this->actingAs($systemAdmin)
        ->post(route('admin.cancellations.review', $cancellation), [
            'responsibility' => CancellationResponsibility::Customer->value,
            'review_notes' => 'Canceló durante preparación por cambio de opinión.',
        ])
        ->assertRedirect();

    $cancellation->refresh();

    expect($cancellation->responsibility)->toBe(CancellationResponsibility::Customer)
        ->and($cancellation->review_status->value)->toBe('resolved')
        ->and($cancellation->reviewed_by_user_id)->toBe($systemAdmin->id);
});
