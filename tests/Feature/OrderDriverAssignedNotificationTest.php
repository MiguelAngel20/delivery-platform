<?php

use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Driver;
use App\Models\NotificationIdempotencyKey;
use App\Models\Order;
use App\Models\User;
use App\Notifications\Orders\OrderDriverAssignedNotification;
use App\Services\Notifications\RideNotificationDispatcher;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

test('driver assigned notifies the customer once with persistent dedupe', function () {
    Queue::fake();

    $customerUser = User::factory()->customer()->create();
    $customer = Customer::factory()->for($customerUser)->create();
    $driver = Driver::factory()->create();
    $order = Order::factory()->create([
        'customer_id' => $customer->id,
        'assigned_driver_id' => $driver->id,
        'order_status' => OrderStatus::DriverAssigned,
    ]);

    $dispatcher = app(RideNotificationDispatcher::class);
    $dispatcher->driverAssigned($order);
    $dispatcher->driverAssigned($order);

    expect($customerUser->fresh()->notifications()->count())->toBe(1);

    $notification = $customerUser->notifications()->first();
    expect($notification->type)->toBe(OrderDriverAssignedNotification::class)
        ->and($notification->data['dedupe_key'] ?? null)->toBe('order:'.$order->id.':driver-assigned')
        ->and(NotificationIdempotencyKey::query()->where('status', 'sent')->count())->toBe(1);
});

test('driver assigned notification is critical and persistent', function () {
    Notification::fake();

    $customerUser = User::factory()->customer()->create();
    $customer = Customer::factory()->for($customerUser)->create();
    $driver = Driver::factory()->create();
    $order = Order::factory()->create([
        'customer_id' => $customer->id,
        'assigned_driver_id' => $driver->id,
        'order_status' => OrderStatus::DriverAssigned,
    ]);

    app(RideNotificationDispatcher::class)->driverAssigned($order);

    Notification::assertSentTo(
        $customerUser,
        OrderDriverAssignedNotification::class,
        fn (OrderDriverAssignedNotification $n): bool => $n->isCritical()
            && $n->requiresPersistentDedupe()
            && $n->dedupeKey() === 'order:'.$order->id.':driver-assigned',
    );
});
