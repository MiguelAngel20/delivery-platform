<?php

use App\Contracts\PushProvider;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Jobs\Notifications\SendPushToUserJob;
use App\Models\Customer;
use App\Models\NotificationIdempotencyKey;
use App\Models\Order;
use App\Models\PushDevice;
use App\Models\User;
use App\Notifications\Orders\DriverRatingPromptNotification;
use App\Notifications\Orders\NewBusinessOrderNotification;
use App\Notifications\Orders\OrderCancelledNotification;
use App\Notifications\Orders\OrderStatusChangedNotification;
use App\Services\Notifications\NotificationIdempotencyService;
use App\Services\Push\PushNotificationService;
use App\Support\PushMessage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;

test('critical order status notify twice produces a single database notification', function () {
    Queue::fake();

    $customerUser = User::factory()->customer()->create();
    $customer = Customer::factory()->for($customerUser)->create();
    $order = Order::factory()->create([
        'customer_id' => $customer->id,
        'order_status' => OrderStatus::Delivered,
    ]);

    $notification = new OrderStatusChangedNotification(
        $order,
        OrderStatus::Delivered,
        UserRole::Customer,
    );

    $customerUser->notify($notification);
    $customerUser->notify($notification);

    expect($customerUser->fresh()->notifications()->count())->toBe(1)
        ->and(NotificationIdempotencyKey::query()->count())->toBe(1);
});

test('distinct notification types for the same order are both allowed', function () {
    Queue::fake();

    $customerUser = User::factory()->customer()->create();
    $customer = Customer::factory()->for($customerUser)->create();
    $order = Order::factory()->create([
        'customer_id' => $customer->id,
        'order_status' => OrderStatus::Delivered,
    ]);

    $customerUser->notify(new OrderStatusChangedNotification(
        $order,
        OrderStatus::Preparing,
        UserRole::Customer,
    ));
    $customerUser->notify(new OrderStatusChangedNotification(
        $order,
        OrderStatus::Delivered,
        UserRole::Customer,
    ));

    expect($customerUser->fresh()->notifications()->count())->toBe(2);
});

test('different recipients each receive their own critical notification', function () {
    Queue::fake();

    $userA = User::factory()->customer()->create();
    $userB = User::factory()->customer()->create();
    $customer = Customer::factory()->for($userA)->create();
    $order = Order::factory()->create([
        'customer_id' => $customer->id,
        'order_status' => OrderStatus::Cancelled,
    ]);

    $userA->notify(new OrderCancelledNotification($order, UserRole::BusinessAdmin));
    $userB->notify(new OrderCancelledNotification($order, UserRole::BusinessAdmin));

    expect($userA->fresh()->notifications()->count())->toBe(1)
        ->and($userB->fresh()->notifications()->count())->toBe(1)
        ->and(NotificationIdempotencyKey::query()->count())->toBe(2);
});

test('cache flush does not allow duplicating a persistently protected event', function () {
    Queue::fake();

    $customerUser = User::factory()->customer()->create();
    $customer = Customer::factory()->for($customerUser)->create();
    $order = Order::factory()->create([
        'customer_id' => $customer->id,
        'order_status' => OrderStatus::PickedUp,
    ]);

    $notification = new OrderStatusChangedNotification(
        $order,
        OrderStatus::PickedUp,
        UserRole::Customer,
    );

    $customerUser->notify($notification);

    Cache::flush();

    $customerUser->notify($notification);

    expect($customerUser->fresh()->notifications()->count())->toBe(1)
        ->and(NotificationIdempotencyKey::query()->count())->toBe(1);
});

test('critical push job sends once even after cache flush', function () {
    $user = User::factory()->create();
    PushDevice::factory()->create(['user_id' => $user->id, 'token' => 'tok-persist']);
    $message = new PushMessage(title: 'Entregado', body: 'Tu pedido fue entregado');

    $provider = Mockery::mock(PushProvider::class);
    $provider->shouldReceive('sendToMany')->once()->andReturn(['sent' => 1, 'failed' => 0, 'invalidated' => []]);

    $push = new PushNotificationService($provider);
    $idempotency = app(NotificationIdempotencyService::class);
    $job = new SendPushToUserJob($user->id, $message, 'order:9:delivered', true);

    $job->handle($push, $idempotency);

    Cache::flush();

    $job->handle($push, $idempotency);
});

test('rating prompt requires persistent dedupe', function () {
    $order = Order::factory()->create();
    $notification = new DriverRatingPromptNotification($order);

    expect($notification->requiresPersistentDedupe())->toBeTrue()
        ->and($notification->dedupeKey())->toBe('rating-prompt:'.$order->id);
});

test('ephemeral driver-offer style keys do not write persistent rows from RideNotification alone', function () {
    Queue::fake();

    $user = User::factory()->customer()->create();
    $customer = Customer::factory()->for($user)->create();
    $order = Order::factory()->create(['customer_id' => $customer->id]);

    // NewBusinessOrderNotification is not marked persistent — only cache TTL.
    $user->notify(new NewBusinessOrderNotification($order));
    Cache::flush();
    $user->notify(new NewBusinessOrderNotification($order));

    expect($user->fresh()->notifications()->count())->toBe(2)
        ->and(NotificationIdempotencyKey::query()->count())->toBe(0);
});
