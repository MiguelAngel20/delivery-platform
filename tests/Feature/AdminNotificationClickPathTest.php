<?php

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\User;
use App\Notifications\Orders\AdminAffiliateOrderNotification;
use App\Notifications\Orders\OrderCancelledNotification;
use App\Notifications\Orders\OrderStatusChangedNotification;
use App\Support\NotificationPaths;

test('admin order notifications open the existing order page', function () {
    $admin = User::factory()->systemAdmin()->create();
    $order = Order::factory()->create([
        'order_status' => OrderStatus::Preparing,
    ]);

    $path = NotificationPaths::adminOrder($order);

    $status = new OrderStatusChangedNotification($order, OrderStatus::Preparing, UserRole::SystemAdmin);
    $cancelled = new OrderCancelledNotification($order, UserRole::SystemAdmin);
    $affiliate = new AdminAffiliateOrderNotification($order);

    expect($path)->toBe('/admin/orders/'.$order->order_number)
        ->and($status->clickPath())->toBe($path)
        ->and($status->toArray($admin)['click_path'])->toBe($path)
        ->and($status->pushData()['click_path'])->toBe($path)
        ->and($cancelled->clickPath())->toBe($path)
        ->and($cancelled->toArray($admin)['click_path'])->toBe($path)
        ->and($cancelled->pushData()['click_path'])->toBe($path)
        ->and($affiliate->clickPath())->toBe($path)
        ->and($affiliate->pushData()['click_path'])->toBe($path);

    $this->actingAs($admin)->get($path)->assertOk();

    $this->actingAs($admin)
        ->get('/admin/orders/'.$order->id)
        ->assertNotFound();
});
