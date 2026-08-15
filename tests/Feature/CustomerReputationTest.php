<?php

use App\Enums\CancellationReasonCode;
use App\Enums\CancellationResponsibility;
use App\Enums\CustomerTrustLevel;
use App\Enums\IncidentStatus;
use App\Enums\IncidentType;
use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Incident;
use App\Models\Order;
use App\Models\OrderCancellation;
use App\Models\User;
use App\Services\Reputation\CustomerReputationService;

function customerRepMakeCustomer(): Customer
{
    return Customer::factory()->create();
}

test('new customer starts NEW', function () {
    $customer = customerRepMakeCustomer();

    $metrics = app(CustomerReputationService::class)->recalculate($customer);

    expect($customer->fresh()->trust_level)->toBe(CustomerTrustLevel::New)
        ->and($metrics->trust_level)->toBe(CustomerTrustLevel::New)
        ->and($metrics->completed_orders)->toBe(0)
        ->and($metrics->total_orders)->toBe(0)
        ->and((float) $metrics->trust_score)->toBe((float) config('reputation.customer.base_score'));
});

test('completed orders increase customer metrics', function () {
    $customer = customerRepMakeCustomer();

    Order::factory()->count(3)->create([
        'customer_id' => $customer->id,
        'order_status' => OrderStatus::Delivered,
        'delivered_at' => now(),
    ]);

    $metrics = app(CustomerReputationService::class)->recalculate($customer);

    expect($metrics->total_orders)->toBe(3)
        ->and($metrics->completed_orders)->toBe(3)
        ->and($customer->fresh()->trust_level)->toBe(CustomerTrustLevel::Good)
        ->and((float) $metrics->trust_score)->toBeGreaterThan((float) config('reputation.customer.base_score'));
});

test('customer-responsible cancellation impacts reputation', function () {
    $customer = customerRepMakeCustomer();
    $order = Order::factory()->create([
        'customer_id' => $customer->id,
        'order_status' => OrderStatus::Cancelled,
    ]);
    OrderCancellation::factory()->create([
        'order_id' => $order->id,
        'responsibility' => CancellationResponsibility::Customer,
        'previous_order_status' => OrderStatus::Preparing,
        'reason_code' => CancellationReasonCode::CustomerChangedMind,
    ]);

    $metrics = app(CustomerReputationService::class)->recalculate($customer);

    expect($metrics->cancelled_orders)->toBe(1)
        ->and($metrics->late_cancellations)->toBe(1)
        ->and((float) $metrics->trust_score)->toBeLessThan((float) config('reputation.customer.base_score'));
});

test('business-responsible cancellation does not penalize customer', function () {
    $customer = customerRepMakeCustomer();
    $order = Order::factory()->create([
        'customer_id' => $customer->id,
        'order_status' => OrderStatus::Cancelled,
    ]);
    OrderCancellation::factory()->create([
        'order_id' => $order->id,
        'responsibility' => CancellationResponsibility::Business,
        'previous_order_status' => OrderStatus::Preparing,
        'reason_code' => CancellationReasonCode::BusinessOutOfStock,
    ]);

    $metrics = app(CustomerReputationService::class)->recalculate($customer);

    expect($metrics->cancelled_orders)->toBe(1)
        ->and($metrics->late_cancellations)->toBe(0)
        ->and($metrics->rejected_at_delivery)->toBe(0)
        ->and((float) $metrics->trust_score)->toBe((float) config('reputation.customer.base_score'))
        ->and($customer->fresh()->trust_level)->toBe(CustomerTrustLevel::New);
});

test('resolved incident with CUSTOMER responsibility impacts reputation', function () {
    $customer = customerRepMakeCustomer();
    $order = Order::factory()->create([
        'customer_id' => $customer->id,
        'order_status' => OrderStatus::OnTheWay,
    ]);
    Incident::factory()->create([
        'order_id' => $order->id,
        'customer_id' => $customer->id,
        'type' => IncidentType::CustomerRefusedOrder,
        'status' => IncidentStatus::Resolved,
        'resolution' => 'El cliente rechazó el pedido.',
        'resolved_at' => now(),
    ]);

    $metrics = app(CustomerReputationService::class)->recalculate($customer);

    expect($metrics->incident_count)->toBe(1)
        ->and($metrics->responsible_incidents)->toBe(1)
        ->and((float) $metrics->trust_score)->toBeLessThan((float) config('reputation.customer.base_score'));
});

test('customer profile backfills completed orders from delivered history', function () {
    $user = User::factory()->customer()->create();
    $customer = Customer::factory()->forUser($user)->create();
    Order::factory()->create([
        'customer_id' => $customer->id,
        'order_status' => OrderStatus::Delivered,
        'delivered_at' => now(),
    ]);

    expect($customer->metrics)->toBeNull();

    $this->actingAs($user)
        ->get(route('customer.profile.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('customer/profile/index')
            ->where('reputation.completed_orders', 1));

    expect($customer->fresh()->metrics?->completed_orders)->toBe(1);
});
