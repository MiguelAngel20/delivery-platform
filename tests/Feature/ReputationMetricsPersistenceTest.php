<?php

use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\CustomerMetric;
use App\Models\Driver;
use App\Models\DriverMetric;
use App\Models\Order;
use App\Models\User;
use App\Services\Reputation\CustomerReputationService;
use App\Services\Reputation\DriverReputationService;
use Illuminate\Database\UniqueConstraintViolationException;

function reputationPersistenceCustomer(): Customer
{
    return Customer::factory()->create();
}

function reputationPersistenceDriver(): Driver
{
    $user = User::factory()->driver()->create();

    return Driver::factory()->approved()->forUser($user)->create();
}

test('first customer recalculate creates a single metrics row', function () {
    $customer = reputationPersistenceCustomer();

    expect(CustomerMetric::query()->where('customer_id', $customer->id)->count())->toBe(0);

    $metrics = app(CustomerReputationService::class)->recalculate($customer);

    expect(CustomerMetric::query()->where('customer_id', $customer->id)->count())->toBe(1)
        ->and($metrics->customer_id)->toBe($customer->id)
        ->and($metrics->total_orders)->toBe(0)
        ->and($metrics->completed_orders)->toBe(0)
        ->and($metrics->last_recalculated_at)->not->toBeNull();
});

test('second customer recalculate updates the same metrics row', function () {
    $customer = reputationPersistenceCustomer();
    $service = app(CustomerReputationService::class);

    $first = $service->recalculate($customer);
    $firstId = $first->id;

    Order::factory()->count(2)->create([
        'customer_id' => $customer->id,
        'order_status' => OrderStatus::Delivered,
        'delivered_at' => now(),
    ]);

    $second = $service->recalculate($customer->fresh());

    expect(CustomerMetric::query()->where('customer_id', $customer->id)->count())->toBe(1)
        ->and($second->id)->toBe($firstId)
        ->and($second->total_orders)->toBe(2)
        ->and($second->completed_orders)->toBe(2)
        ->and((float) $second->trust_score)->toBeGreaterThan((float) $first->trust_score);
});

test('multiple consecutive customer recalculates never create duplicate metrics', function () {
    $customer = reputationPersistenceCustomer();
    $service = app(CustomerReputationService::class);

    Order::factory()->create([
        'customer_id' => $customer->id,
        'order_status' => OrderStatus::Delivered,
        'delivered_at' => now(),
    ]);

    foreach (range(1, 8) as $_) {
        $service->recalculate($customer->fresh());
    }

    expect(CustomerMetric::query()->where('customer_id', $customer->id)->count())->toBe(1)
        ->and($customer->fresh()->metrics?->completed_orders)->toBe(1);
});

test('customer recalculate does not throw UniqueConstraintViolationException on repeated upserts', function () {
    $customer = reputationPersistenceCustomer();
    $service = app(CustomerReputationService::class);

    expect(fn () => $service->recalculate($customer))
        ->not->toThrow(UniqueConstraintViolationException::class);

    expect(fn () => $service->recalculate($customer->fresh()))
        ->not->toThrow(UniqueConstraintViolationException::class);

    expect(CustomerMetric::query()->where('customer_id', $customer->id)->count())->toBe(1);
});

test('concurrent-style customer upserts leave exactly one metrics row', function () {
    $customer = reputationPersistenceCustomer();
    $service = app(CustomerReputationService::class);
    $now = now();

    // Simulate the race window: two writers both attempt insert/update via upsert
    // (MySQL INSERT ... ON DUPLICATE KEY UPDATE) for the same unique customer_id.
    CustomerMetric::query()->upsert(
        [
            [
                'customer_id' => $customer->id,
                'total_orders' => 0,
                'completed_orders' => 0,
                'cancelled_orders' => 0,
                'late_cancellations' => 0,
                'rejected_at_delivery' => 0,
                'payment_incidents' => 0,
                'incident_count' => 0,
                'responsible_incidents' => 0,
                'trust_score' => 50,
                'trust_level' => 'new',
                'last_recalculated_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'customer_id' => $customer->id,
                'total_orders' => 1,
                'completed_orders' => 1,
                'cancelled_orders' => 0,
                'late_cancellations' => 0,
                'rejected_at_delivery' => 0,
                'payment_incidents' => 0,
                'incident_count' => 0,
                'responsible_incidents' => 0,
                'trust_score' => 55,
                'trust_level' => 'good',
                'last_recalculated_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ],
        uniqueBy: ['customer_id'],
        update: [
            'total_orders',
            'completed_orders',
            'trust_score',
            'trust_level',
            'last_recalculated_at',
            'updated_at',
        ],
    );

    $service->recalculate($customer);

    expect(CustomerMetric::query()->where('customer_id', $customer->id)->count())->toBe(1);
});

test('markBlocked still works after upsert recalculate', function () {
    $customer = reputationPersistenceCustomer();
    $service = app(CustomerReputationService::class);

    $blocked = $service->markBlocked($customer);

    expect(CustomerMetric::query()->where('customer_id', $customer->id)->count())->toBe(1)
        ->and($blocked->trust_level->value)->toBe('blocked')
        ->and($customer->fresh()->trust_level->value)->toBe('blocked');

    $cleared = $service->clearBlocked($customer->fresh());

    expect(CustomerMetric::query()->where('customer_id', $customer->id)->count())->toBe(1)
        ->and($cleared->trust_level->value)->not->toBe('blocked');
});

test('first driver recalculate creates a single metrics row', function () {
    $driver = reputationPersistenceDriver();

    expect(DriverMetric::query()->where('driver_id', $driver->id)->count())->toBe(0);

    $metrics = app(DriverReputationService::class)->recalculate($driver);

    expect(DriverMetric::query()->where('driver_id', $driver->id)->count())->toBe(1)
        ->and($metrics->driver_id)->toBe($driver->id)
        ->and($metrics->completed_orders)->toBe(0)
        ->and($metrics->last_recalculated_at)->not->toBeNull();
});

test('second driver recalculate updates the same metrics row', function () {
    $driver = reputationPersistenceDriver();
    $service = app(DriverReputationService::class);

    $first = $service->recalculate($driver);
    $firstId = $first->id;

    Order::factory()->create([
        'assigned_driver_id' => $driver->id,
        'order_status' => OrderStatus::Delivered,
        'delivered_at' => now(),
    ]);

    $second = $service->recalculate($driver->fresh());

    expect(DriverMetric::query()->where('driver_id', $driver->id)->count())->toBe(1)
        ->and($second->id)->toBe($firstId)
        ->and($second->completed_orders)->toBe(1)
        ->and((float) $second->trust_score)->toBeGreaterThan((float) $first->trust_score);
});

test('multiple consecutive driver recalculates never create duplicate metrics', function () {
    $driver = reputationPersistenceDriver();
    $service = app(DriverReputationService::class);

    Order::factory()->create([
        'assigned_driver_id' => $driver->id,
        'order_status' => OrderStatus::Delivered,
        'delivered_at' => now(),
    ]);

    foreach (range(1, 8) as $_) {
        $service->recalculate($driver->fresh());
    }

    expect(DriverMetric::query()->where('driver_id', $driver->id)->count())->toBe(1)
        ->and($driver->fresh()->metrics?->completed_orders)->toBe(1);
});

test('driver recalculate does not throw UniqueConstraintViolationException on repeated upserts', function () {
    $driver = reputationPersistenceDriver();
    $service = app(DriverReputationService::class);

    expect(fn () => $service->recalculate($driver))
        ->not->toThrow(UniqueConstraintViolationException::class);

    expect(fn () => $service->recalculate($driver->fresh()))
        ->not->toThrow(UniqueConstraintViolationException::class);

    expect(DriverMetric::query()->where('driver_id', $driver->id)->count())->toBe(1);
});

test('concurrent-style driver upserts leave exactly one metrics row', function () {
    $driver = reputationPersistenceDriver();
    $now = now();

    DriverMetric::query()->upsert(
        [
            [
                'driver_id' => $driver->id,
                'offered_orders' => 0,
                'accepted_orders' => 0,
                'rejected_orders' => 0,
                'completed_orders' => 0,
                'cancelled_orders' => 0,
                'responsible_cancellations' => 0,
                'incident_count' => 0,
                'responsible_incidents' => 0,
                'average_rating' => null,
                'total_ratings' => 0,
                'trust_score' => 50,
                'last_recalculated_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'driver_id' => $driver->id,
                'offered_orders' => 1,
                'accepted_orders' => 1,
                'rejected_orders' => 0,
                'completed_orders' => 1,
                'cancelled_orders' => 0,
                'responsible_cancellations' => 0,
                'incident_count' => 0,
                'responsible_incidents' => 0,
                'average_rating' => null,
                'total_ratings' => 0,
                'trust_score' => 55,
                'last_recalculated_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ],
        uniqueBy: ['driver_id'],
        update: [
            'offered_orders',
            'accepted_orders',
            'completed_orders',
            'trust_score',
            'last_recalculated_at',
            'updated_at',
        ],
    );

    app(DriverReputationService::class)->recalculate($driver);

    expect(DriverMetric::query()->where('driver_id', $driver->id)->count())->toBe(1);
});
