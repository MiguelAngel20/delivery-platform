<?php

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Listeners\Notifications\HandleRideNotificationSent;
use App\Models\Customer;
use App\Models\NotificationIdempotencyKey;
use App\Models\Order;
use App\Models\User;
use App\Notifications\Channels\FcmChannel;
use App\Notifications\Orders\OrderStatusChangedNotification;
use App\Services\Notifications\NotificationIdempotencyService;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

test('notification failed without database row reopens claim for retry and keeps the row', function () {
    Queue::fake();

    $user = User::factory()->customer()->create();
    $customer = Customer::factory()->for($user)->create();
    $order = Order::factory()->create([
        'customer_id' => $customer->id,
        'order_status' => OrderStatus::Delivered,
    ]);

    $notification = new OrderStatusChangedNotification(
        $order,
        OrderStatus::Delivered,
        UserRole::Customer,
    );

    $idempotency = app(NotificationIdempotencyService::class);
    $key = $idempotency->notificationKey($user->id, $notification->dedupeKey());

    expect($notification->via($user))->toBe(['database', FcmChannel::class]);

    $row = NotificationIdempotencyKey::query()->where('idempotency_key', $key)->first();
    expect($row)->not->toBeNull()
        ->and($row->status)->toBe(NotificationIdempotencyKey::STATUS_CLAIMED);

    event(new NotificationFailed($user, $notification, 'database', [
        'exception' => new RuntimeException('database channel exploded'),
    ]));

    $row->refresh();
    expect($row->status)->toBe(NotificationIdempotencyKey::STATUS_FAILED)
        ->and($row->last_error)->toContain('database channel exploded')
        ->and(NotificationIdempotencyKey::query()->count())->toBe(1);

    Cache::flush();

    expect($notification->via($user))->toBe(['database', FcmChannel::class]);

    $row->refresh();
    expect($row->status)->toBe(NotificationIdempotencyKey::STATUS_CLAIMED)
        ->and($row->attempts)->toBe(2);
});

test('notification failed after database side effect keeps claim sent and blocks duplicate', function () {
    Queue::fake();

    $user = User::factory()->customer()->create();
    $customer = Customer::factory()->for($user)->create();
    $order = Order::factory()->create([
        'customer_id' => $customer->id,
        'order_status' => OrderStatus::Delivered,
    ]);

    $notification = new OrderStatusChangedNotification(
        $order,
        OrderStatus::Delivered,
        UserRole::Customer,
    );

    $user->notify($notification);

    expect($user->fresh()->notifications()->count())->toBe(1);

    $idempotency = app(NotificationIdempotencyService::class);
    $key = $idempotency->notificationKey($user->id, $notification->dedupeKey());

    expect(NotificationIdempotencyKey::query()->where('idempotency_key', $key)->value('status'))
        ->toBe(NotificationIdempotencyKey::STATUS_SENT);

    event(new NotificationFailed($user, $notification, FcmChannel::class, [
        'exception' => new RuntimeException('fcm after db'),
    ]));

    expect(NotificationIdempotencyKey::query()->where('idempotency_key', $key)->value('status'))
        ->toBe(NotificationIdempotencyKey::STATUS_SENT);

    Cache::flush();
    $user->notify($notification);

    expect($user->fresh()->notifications()->count())->toBe(1);
});

test('ride notification sent listener is registered for NotificationSent', function () {
    Event::fake([
        NotificationSent::class,
    ]);

    // Discovery smoke: listener class is constructible and bound.
    expect(app(HandleRideNotificationSent::class))
        ->toBeInstanceOf(HandleRideNotificationSent::class);
});
