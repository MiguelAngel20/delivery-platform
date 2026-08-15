<?php

use App\Contracts\PushProvider;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\NotificationPreference;
use App\Models\Order;
use App\Models\PushDevice;
use App\Models\User;
use App\Notifications\Orders\OrderStatusChangedNotification;
use App\Services\Notifications\NotificationPreferenceService;

test('disabled optional notification does not send push', function () {
    $user = User::factory()->customer()->create();
    NotificationPreference::factory()->create([
        'user_id' => $user->id,
        'push_enabled' => true,
        'order_updates' => false,
    ]);
    PushDevice::factory()->create(['user_id' => $user->id]);

    $provider = Mockery::mock(PushProvider::class);
    $provider->shouldNotReceive('sendToMany');
    $provider->shouldNotReceive('send');
    $this->app->instance(PushProvider::class, $provider);

    $order = Order::factory()->create();

    $user->notify(new OrderStatusChangedNotification(
        $order,
        OrderStatus::Preparing,
        UserRole::Customer,
    ));
});

test('critical active-order update still sends push when optional prefs off', function () {
    $user = User::factory()->customer()->create();
    NotificationPreference::factory()->create([
        'user_id' => $user->id,
        'push_enabled' => false,
        'order_updates' => false,
    ]);
    PushDevice::factory()->create(['user_id' => $user->id, 'token' => 'critical-token']);

    $provider = Mockery::mock(PushProvider::class);
    $provider->shouldReceive('sendToMany')
        ->once()
        ->andReturn(['sent' => 1, 'failed' => 0, 'invalidated' => []]);
    $this->app->instance(PushProvider::class, $provider);

    $order = Order::factory()->create();

    $user->notify(new OrderStatusChangedNotification(
        $order,
        OrderStatus::Cancelled,
        UserRole::Customer,
    ));
});

test('user can update own notification preferences', function () {
    $user = User::factory()->customer()->create();

    $this->actingAs($user)
        ->put(route('customer.profile.notifications.update'), [
            'push_enabled' => false,
            'order_updates' => false,
            'system_updates' => true,
            'custom_order_updates' => true,
            'incident_updates' => true,
        ])
        ->assertRedirect();

    $preference = app(NotificationPreferenceService::class)->forUser($user->fresh());

    expect($preference->push_enabled)->toBeFalse()
        ->and($preference->order_updates)->toBeFalse()
        ->and($preference->system_updates)->toBeTrue();
});
