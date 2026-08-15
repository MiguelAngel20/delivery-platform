<?php

use App\Enums\CancellationReasonCode;
use App\Enums\CancellationResponsibility;
use App\Enums\IncidentStatus;
use App\Enums\IncidentType;
use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Driver;
use App\Models\DriverRating;
use App\Models\Incident;
use App\Models\Order;
use App\Models\OrderCancellation;
use App\Models\User;
use App\Services\Reputation\DriverReputationService;

function driverRepMakeDriver(): Driver
{
    $user = User::factory()->driver()->create();

    return Driver::factory()->approved()->forUser($user)->create();
}

test('delivered order increments completed orders', function () {
    $driver = driverRepMakeDriver();

    Order::factory()->create([
        'assigned_driver_id' => $driver->id,
        'order_status' => OrderStatus::Delivered,
        'delivered_at' => now(),
    ]);

    $metrics = app(DriverReputationService::class)->recalculate($driver);

    expect($metrics->completed_orders)->toBe(1)
        ->and($metrics->total_ratings)->toBe(0)
        ->and($metrics->average_rating)->toBeNull()
        ->and((float) $metrics->trust_score)->toBeGreaterThan((float) config('reputation.driver.base_score'));
});

test('driver-responsible cancellation impacts metrics', function () {
    $driver = driverRepMakeDriver();
    $order = Order::factory()->create([
        'assigned_driver_id' => $driver->id,
        'order_status' => OrderStatus::Cancelled,
    ]);
    OrderCancellation::factory()->create([
        'order_id' => $order->id,
        'responsibility' => CancellationResponsibility::Driver,
        'previous_order_status' => OrderStatus::DriverAssigned,
        'reason_code' => CancellationReasonCode::DriverCannotComplete,
    ]);

    $metrics = app(DriverReputationService::class)->recalculate($driver);

    expect($metrics->cancelled_orders)->toBe(1)
        ->and($metrics->responsible_cancellations)->toBe(1)
        ->and((float) $metrics->trust_score)->toBeLessThan((float) config('reputation.driver.base_score'));
});

test('business-responsible incident does not penalize driver', function () {
    $driver = driverRepMakeDriver();
    $order = Order::factory()->create([
        'assigned_driver_id' => $driver->id,
        'order_status' => OrderStatus::Preparing,
    ]);
    Incident::factory()->create([
        'order_id' => $order->id,
        'driver_id' => $driver->id,
        'type' => IncidentType::BusinessDelay,
        'status' => IncidentStatus::Resolved,
        'resolution' => 'El negocio se retrasó.',
        'resolved_at' => now(),
    ]);

    $metrics = app(DriverReputationService::class)->recalculate($driver);

    expect($metrics->incident_count)->toBe(1)
        ->and($metrics->responsible_incidents)->toBe(0)
        ->and((float) $metrics->trust_score)->toBe((float) config('reputation.driver.base_score'));
});

test('driver cannot edit own ratings', function () {
    $driverUser = User::factory()->driver()->create();
    $driver = Driver::factory()->approved()->forUser($driverUser)->create();
    $customerUser = User::factory()->customer()->create();
    $customer = Customer::factory()->forUser($customerUser)->create();
    $order = Order::factory()->create([
        'customer_id' => $customer->id,
        'assigned_driver_id' => $driver->id,
        'order_status' => OrderStatus::Delivered,
        'delivered_at' => now(),
    ]);
    $rating = DriverRating::query()->create([
        'order_id' => $order->id,
        'driver_id' => $driver->id,
        'customer_id' => $customer->id,
        'overall_rating' => 5,
    ]);

    expect($driverUser->can('update', $rating))->toBeFalse()
        ->and($driverUser->can('delete', $rating))->toBeFalse();

    $this->actingAs($driverUser)
        ->post(route('customer.orders.ratings.store', $order), [
            'overall_rating' => 1,
        ])
        ->assertForbidden();
});

test('business cannot edit driver ratings', function () {
    $customerUser = User::factory()->customer()->create();
    $customer = Customer::factory()->forUser($customerUser)->create();
    $driver = driverRepMakeDriver();
    $order = Order::factory()->create([
        'customer_id' => $customer->id,
        'assigned_driver_id' => $driver->id,
        'order_status' => OrderStatus::Delivered,
        'delivered_at' => now(),
    ]);
    $rating = DriverRating::query()->create([
        'order_id' => $order->id,
        'driver_id' => $driver->id,
        'customer_id' => $customer->id,
        'overall_rating' => 4,
    ]);
    $businessUser = User::factory()->businessAdmin()->create();

    expect($businessUser->can('update', $rating))->toBeFalse()
        ->and($businessUser->can('delete', $rating))->toBeFalse();

    $this->actingAs($businessUser)
        ->post(route('customer.orders.ratings.store', $order), [
            'overall_rating' => 1,
        ])
        ->assertForbidden();
});

test('customer cannot edit metrics', function () {
    $customerUser = User::factory()->customer()->create();
    $customer = Customer::factory()->forUser($customerUser)->create();

    $this->actingAs($customerUser)
        ->get(route('admin.customers.show', $customer))
        ->assertForbidden();

    $status = $this->actingAs($customerUser)
        ->put(route('admin.customers.show', $customer), [
            'trust_score' => 99,
            'completed_orders' => 999,
        ])
        ->status();

    expect($status)->toBeIn([403, 405]);
});

test('system admin can view metrics', function () {
    $customer = Customer::factory()->create();
    $driver = driverRepMakeDriver();
    app(DriverReputationService::class)->recalculate($driver);
    $admin = User::factory()->systemAdmin()->create();

    $this->actingAs($admin)
        ->get(route('admin.customers.show', $customer))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/customers/show')
            ->has('customer.trust_level')
            ->has('customer.trust_score')
            ->has('customer.completed_orders')
            ->has('customer.cancelled_orders')
            ->has('customer.late_cancellations')
            ->has('customer.incident_count'));

    $this->actingAs($admin)
        ->get(route('admin.drivers.show', $driver))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/drivers/show')
            ->has('driver.trust_score')
            ->has('driver.completed_orders')
            ->has('driver.average_rating')
            ->has('driver.total_ratings'));
});
