<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Support\OrderData;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('customer timeline exposes five simplified milestones', function () {
    $order = Order::factory()->create([
        'order_status' => OrderStatus::PendingBusiness,
    ]);

    OrderStatusHistory::factory()->create([
        'order_id' => $order->id,
        'status' => OrderStatus::PendingBusiness,
    ]);

    $timeline = OrderData::customerTimeline($order->fresh(['statusHistory']));

    expect($timeline)->toHaveCount(5)
        ->and(collect($timeline)->pluck('label')->all())->toBe([
            'Pedido recibido',
            'Preparando tu pedido',
            'Tu pedido va en camino',
            'Tu pedido ya está afuera de tu domicilio',
            'Entregado',
        ]);
});

test('customer timeline marks completed steps when order is on the way to customer', function () {
    $order = Order::factory()->create([
        'order_status' => OrderStatus::OnTheWay,
    ]);

    foreach ([
        OrderStatus::PendingBusiness,
        OrderStatus::Accepted,
        OrderStatus::Preparing,
        OrderStatus::PickedUp,
        OrderStatus::OnTheWay,
    ] as $status) {
        OrderStatusHistory::factory()->create([
            'order_id' => $order->id,
            'status' => $status,
        ]);
    }

    $timeline = OrderData::customerTimeline($order->fresh(['statusHistory']));

    expect($timeline[0]['done'])->toBeTrue()
        ->and($timeline[1]['done'])->toBeTrue()
        ->and($timeline[2]['done'])->toBeTrue()
        ->and($timeline[3]['current'])->toBeTrue()
        ->and($timeline[3]['done'])->toBeFalse()
        ->and($timeline[4]['done'])->toBeFalse();
});

test('customer timeline marks all steps done when delivered', function () {
    $order = Order::factory()->create([
        'order_status' => OrderStatus::Delivered,
    ]);

    foreach ([
        OrderStatus::PendingBusiness,
        OrderStatus::Preparing,
        OrderStatus::PickedUp,
        OrderStatus::OnTheWay,
        OrderStatus::Delivered,
    ] as $status) {
        OrderStatusHistory::factory()->create([
            'order_id' => $order->id,
            'status' => $status,
        ]);
    }

    $timeline = OrderData::customerTimeline($order->fresh(['statusHistory']));

    expect(collect($timeline)->every(fn (array $step): bool => $step['done'] === true))->toBeTrue()
        ->and(collect($timeline)->contains(fn (array $step): bool => ($step['current'] ?? false) === true))->toBeFalse();
});

test('internal timeline keeps full status history for admin views', function () {
    $order = Order::factory()->create([
        'order_status' => OrderStatus::DriverAssigned,
    ]);

    foreach ([
        OrderStatus::PendingBusiness,
        OrderStatus::Accepted,
        OrderStatus::DriverAssigned,
    ] as $status) {
        OrderStatusHistory::factory()->create([
            'order_id' => $order->id,
            'status' => $status,
        ]);
    }

    $timeline = OrderData::timeline($order->fresh(['statusHistory']));

    expect($timeline)->toHaveCount(3)
        ->and($timeline[0]['label'])->toBe('Nuevo')
        ->and($timeline[1]['label'])->toBe('Aceptado')
        ->and($timeline[2]['label'])->toBe('Repartidor asignado')
        ->and($timeline[2]['current'])->toBeTrue();
});

test('customer order detail includes simplified customer timeline', function () {
    $order = Order::factory()->create([
        'order_status' => OrderStatus::Preparing,
    ]);

    OrderStatusHistory::factory()->create([
        'order_id' => $order->id,
        'status' => OrderStatus::PendingBusiness,
    ]);
    OrderStatusHistory::factory()->create([
        'order_id' => $order->id,
        'status' => OrderStatus::Preparing,
    ]);

    $payload = OrderData::transform($order->fresh([
        'items.options',
        'addresses',
        'statusHistory',
        'branch.business',
        'customer.user',
        'customer.metrics',
        'assignedDriver.user',
        'financial',
        'financialTransactions',
        'payment',
        'cancellation.cancelledBy',
        'incidents',
        'driverRating',
        'quotes.items',
    ]));

    expect($payload)->toHaveKey('customer_timeline')
        ->and($payload['customer_timeline'])->toHaveCount(5)
        ->and($payload['customer_timeline'][1]['current'])->toBeTrue()
        ->and($payload['timeline'])->toHaveCount(2);
});
