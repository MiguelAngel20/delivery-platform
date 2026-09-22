<?php

use App\Enums\OrderStatus;
use App\Models\Driver;
use App\Models\Order;
use App\Models\User;

test('admin order show includes operation summary fields', function () {
    $admin = User::factory()->systemAdmin()->create();
    $driverUser = User::factory()->driver()->create([
        'phone' => '9631112233',
    ]);
    $driver = Driver::factory()->approved()->forUser($driverUser)->create();

    $order = Order::factory()->create([
        'order_status' => OrderStatus::Preparing,
        'estimated_preparation_minutes' => 25,
        'assigned_driver_id' => $driver->id,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.orders.show', $order))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/orders/show')
            ->where('order.estimated_preparation_minutes', 25)
            ->where('order.business_status_label', OrderStatus::Preparing->label())
            ->where('order.driver.name', $driverUser->name)
            ->where('order.driver.phone', '9631112233')
            ->where('order.restaurant.name', $order->branch?->business?->name));
});

test('admin orders inbox redirects to the pedidos list', function () {
    $admin = User::factory()->systemAdmin()->create();

    $this->actingAs($admin)
        ->get(route('admin.orders.inbox', ['search' => 'CHIS-123']))
        ->assertRedirect(route('admin.orders.index', ['search' => 'CHIS-123']));
});

test('admin orders list sorts pending before kitchen before delivered', function () {
    $admin = User::factory()->systemAdmin()->create();

    $delivered = Order::factory()->create([
        'order_status' => OrderStatus::Delivered,
        'created_at' => now()->subMinute(),
    ]);
    $preparing = Order::factory()->create([
        'order_status' => OrderStatus::Preparing,
        'created_at' => now()->subMinutes(2),
    ]);
    $pending = Order::factory()->create([
        'order_status' => OrderStatus::PendingPlatform,
        'created_at' => now()->subMinutes(3),
    ]);

    $this->actingAs($admin)
        ->get(route('admin.orders.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/orders/index')
            ->where('orders.data.0.order_number', $pending->order_number)
            ->where('orders.data.1.order_number', $preparing->order_number)
            ->where('orders.data.2.order_number', $delivered->order_number));
});

test('admin list sort priority groups statuses as expected', function () {
    expect(OrderStatus::PendingPlatform->adminListSortPriority())->toBe(0)
        ->and(OrderStatus::PendingBusiness->adminListSortPriority())->toBe(0)
        ->and(OrderStatus::Preparing->adminListSortPriority())->toBe(1)
        ->and(OrderStatus::OnTheWay->adminListSortPriority())->toBe(1)
        ->and(OrderStatus::Delivered->adminListSortPriority())->toBe(2)
        ->and(OrderStatus::Cancelled->adminListSortPriority())->toBe(3);
});
