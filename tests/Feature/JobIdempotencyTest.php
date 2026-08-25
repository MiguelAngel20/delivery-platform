<?php

use App\Contracts\PushProvider;
use App\Enums\NotificationPriority;
use App\Enums\OrderStatus;
use App\Jobs\Notifications\SendDriverRatingPromptJob;
use App\Jobs\Notifications\SendPushToUserJob;
use App\Models\Customer;
use App\Models\Driver;
use App\Models\DriverRating;
use App\Models\Order;
use App\Models\PushDevice;
use App\Models\User;
use App\Notifications\Orders\DriverRatingPromptNotification;
use App\Services\Notifications\NotificationIdempotencyService;
use App\Services\Push\PushNotificationService;
use App\Support\PushMessage;
use Illuminate\Support\Facades\Queue;

test('rating prompt job notifies once when handle runs twice', function () {
    Queue::fake();

    $customerUser = User::factory()->customer()->create();
    $customer = Customer::factory()->for($customerUser)->create();
    $driver = Driver::factory()->create();
    $order = Order::factory()->create([
        'customer_id' => $customer->id,
        'assigned_driver_id' => $driver->id,
        'order_status' => OrderStatus::Delivered,
    ]);

    (new SendDriverRatingPromptJob($order->id))->handle();
    (new SendDriverRatingPromptJob($order->id))->handle();

    expect($customerUser->fresh()->notifications()->count())->toBe(1);

    $notification = $customerUser->notifications()->first();
    expect($notification->type)->toBe(DriverRatingPromptNotification::class)
        ->and($notification->data['dedupe_key'] ?? null)->toBe('rating-prompt:'.$order->id);
});

test('rating prompt job skips when driver was already rated', function () {
    Queue::fake();

    $customerUser = User::factory()->customer()->create();
    $customer = Customer::factory()->for($customerUser)->create();
    $driver = Driver::factory()->create();
    $order = Order::factory()->create([
        'customer_id' => $customer->id,
        'assigned_driver_id' => $driver->id,
        'order_status' => OrderStatus::Delivered,
    ]);
    DriverRating::factory()->create([
        'order_id' => $order->id,
        'driver_id' => $driver->id,
        'customer_id' => $customer->id,
    ]);

    (new SendDriverRatingPromptJob($order->id))->handle();

    expect($customerUser->fresh()->notifications()->count())->toBe(0);
});

test('rating prompt unique id is scoped per order', function () {
    $jobA = new SendDriverRatingPromptJob(10);
    $jobB = new SendDriverRatingPromptJob(11);

    expect($jobA->uniqueId())->toBe('10')
        ->and($jobB->uniqueId())->toBe('11')
        ->and($jobA->uniqueId())->not->toBe($jobB->uniqueId());
});

test('duplicate rating prompt dispatches collapse via ShouldBeUnique', function () {
    Queue::fake();

    SendDriverRatingPromptJob::dispatch(42);
    SendDriverRatingPromptJob::dispatch(42);
    SendDriverRatingPromptJob::dispatch(43);

    Queue::assertPushed(SendDriverRatingPromptJob::class, 2);
    Queue::assertPushed(SendDriverRatingPromptJob::class, fn (SendDriverRatingPromptJob $job): bool => $job->orderId === 42);
    Queue::assertPushed(SendDriverRatingPromptJob::class, fn (SendDriverRatingPromptJob $job): bool => $job->orderId === 43);
});

test('rating prompts for different orders both create notifications', function () {
    Queue::fake();

    $customerUser = User::factory()->customer()->create();
    $customer = Customer::factory()->for($customerUser)->create();
    $driver = Driver::factory()->create();

    $orderA = Order::factory()->create([
        'customer_id' => $customer->id,
        'assigned_driver_id' => $driver->id,
        'order_status' => OrderStatus::Delivered,
    ]);
    $orderB = Order::factory()->create([
        'customer_id' => $customer->id,
        'assigned_driver_id' => $driver->id,
        'order_status' => OrderStatus::Delivered,
    ]);

    (new SendDriverRatingPromptJob($orderA->id))->handle();
    (new SendDriverRatingPromptJob($orderB->id))->handle();

    expect($customerUser->fresh()->notifications()->count())->toBe(2);
});

test('push job with same idempotency key sends only once', function () {
    $user = User::factory()->create();
    PushDevice::factory()->create(['user_id' => $user->id, 'token' => 'tok-same']);
    $message = new PushMessage(
        title: 'Pedido listo',
        body: 'Tu pedido está listo',
        data: ['type' => 'test'],
        priority: NotificationPriority::Normal,
    );

    $provider = Mockery::mock(PushProvider::class);
    $provider->shouldReceive('sendToMany')->once()->andReturn(['sent' => 1, 'failed' => 0, 'invalidated' => []]);
    $this->app->instance(PushProvider::class, $provider);

    $push = new PushNotificationService($provider);
    $idempotency = app(NotificationIdempotencyService::class);
    $job = new SendPushToUserJob($user->id, $message, 'order:1:ready');
    $job->handle($push, $idempotency);
    $job->handle($push, $idempotency);
});

test('push jobs with different idempotency keys both send', function () {
    $user = User::factory()->create();
    PushDevice::factory()->create(['user_id' => $user->id, 'token' => 'tok-diff']);
    $message = new PushMessage(title: 'A', body: 'B');

    $provider = Mockery::mock(PushProvider::class);
    $provider->shouldReceive('sendToMany')->twice()->andReturn(['sent' => 1, 'failed' => 0, 'invalidated' => []]);
    $this->app->instance(PushProvider::class, $provider);

    $push = new PushNotificationService($provider);
    $idempotency = app(NotificationIdempotencyService::class);
    (new SendPushToUserJob($user->id, $message, 'order:1:accepted'))->handle($push, $idempotency);
    (new SendPushToUserJob($user->id, $message, 'order:1:on_the_way'))->handle($push, $idempotency);
});

test('push jobs for different users with same logical key both send', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    PushDevice::factory()->create(['user_id' => $userA->id, 'token' => 'tok-a']);
    PushDevice::factory()->create(['user_id' => $userB->id, 'token' => 'tok-b']);
    $message = new PushMessage(title: 'Oferta', body: 'Nuevo pedido');

    $provider = Mockery::mock(PushProvider::class);
    $provider->shouldReceive('sendToMany')->twice()->andReturn(['sent' => 1, 'failed' => 0, 'invalidated' => []]);
    $this->app->instance(PushProvider::class, $provider);

    $push = new PushNotificationService($provider);
    $idempotency = app(NotificationIdempotencyService::class);
    (new SendPushToUserJob($userA->id, $message, 'driver-offer:99'))->handle($push, $idempotency);
    (new SendPushToUserJob($userB->id, $message, 'driver-offer:99'))->handle($push, $idempotency);
});

test('push job without idempotency key can send repeatedly', function () {
    $user = User::factory()->create();
    PushDevice::factory()->create(['user_id' => $user->id, 'token' => 'tok-repeat']);
    $message = new PushMessage(title: 'Ping', body: 'Hello');

    $provider = Mockery::mock(PushProvider::class);
    $provider->shouldReceive('sendToMany')->twice()->andReturn(['sent' => 1, 'failed' => 0, 'invalidated' => []]);
    $this->app->instance(PushProvider::class, $provider);

    $push = new PushNotificationService($provider);
    $idempotency = app(NotificationIdempotencyService::class);
    (new SendPushToUserJob($user->id, $message, null))->handle($push, $idempotency);
    (new SendPushToUserJob($user->id, $message, null))->handle($push, $idempotency);
});

test('push job forgets idempotency key when provider throws so retry can run', function () {
    $user = User::factory()->create();
    PushDevice::factory()->create(['user_id' => $user->id, 'token' => 'tok-retry']);
    $message = new PushMessage(title: 'Fail', body: 'then ok');

    $provider = Mockery::mock(PushProvider::class);
    $provider->shouldReceive('sendToMany')
        ->once()
        ->andThrow(new RuntimeException('FCM down'));
    $provider->shouldReceive('sendToMany')
        ->once()
        ->andReturn(['sent' => 1, 'failed' => 0, 'invalidated' => []]);
    $this->app->instance(PushProvider::class, $provider);

    $push = new PushNotificationService($provider);
    $idempotency = app(NotificationIdempotencyService::class);
    $job = new SendPushToUserJob($user->id, $message, 'order:7:retry');

    expect(fn () => $job->handle($push, $idempotency))->toThrow(RuntimeException::class);

    $job->handle($push, $idempotency);
});
