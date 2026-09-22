<?php

use App\Enums\DriverAvailabilityStatus;
use App\Enums\LoyaltyRewardType;
use App\Enums\OrderStatus;
use App\Models\Driver;
use App\Models\Order;
use App\Models\OrderFinancial;
use App\Models\User;
use App\Services\Drivers\DriverCommissionService;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

test('admin can configure driver commission settings', function () {
    $admin = User::factory()->systemAdmin()->create();
    $user = User::factory()->driver()->create();
    $driver = Driver::factory()->forUser($user)->approved($admin)->create();

    $this->actingAs($admin)
        ->put(route('admin.drivers.commission.update', $driver), [
            'pays_commission' => true,
            'commission_per_order' => 10,
        ])
        ->assertRedirect();

    expect($driver->fresh()->pays_commission)->toBeTrue()
        ->and((string) $driver->fresh()->commission_per_order)->toBe('10.00');
});

test('delivery applies commission and reduces driver earning', function () {
    $admin = User::factory()->systemAdmin()->create();
    $user = User::factory()->driver()->create();
    $driver = Driver::factory()->forUser($user)->approved($admin)->withCommission(10)->create();

    $order = Order::factory()->create([
        'assigned_driver_id' => $driver->id,
        'order_status' => OrderStatus::Delivered,
        'delivered_at' => now(),
        'service_fee' => 50,
    ]);

    OrderFinancial::factory()->create([
        'order_id' => $order->id,
        'service_fee' => 50,
        'driver_earning' => 50,
        'platform_earning' => 0,
        'driver_commission' => 0,
    ]);

    app(DriverCommissionService::class)->applyOnDelivery($order->fresh('financial'), $driver);
    $financial = $order->fresh('financial')->financial;

    expect((string) $financial->driver_commission)->toBe('10.00')
        ->and((string) $financial->driver_earning)->toBe('40.00')
        ->and((string) $financial->platform_earning)->toBe('10.00');
});

test('streak loyalty reward orders skip driver commission', function () {
    $admin = User::factory()->systemAdmin()->create();
    $user = User::factory()->driver()->create();
    $driver = Driver::factory()->forUser($user)->approved($admin)->withCommission(10)->create();

    $order = Order::factory()->create([
        'assigned_driver_id' => $driver->id,
        'order_status' => OrderStatus::Delivered,
        'delivered_at' => now(),
        'service_fee' => 50,
        'service_fee_discount' => 15,
        'loyalty_reward_type' => LoyaltyRewardType::StreakReward,
    ]);

    OrderFinancial::factory()->create([
        'order_id' => $order->id,
        'service_fee' => 50,
        'driver_earning' => 50,
        'platform_earning' => 0,
        'driver_commission' => 0,
    ]);

    app(DriverCommissionService::class)->applyOnDelivery($order->fresh('financial'), $driver);
    $financial = $order->fresh('financial')->financial;

    expect((string) $financial->driver_commission)->toBe('0.00')
        ->and((string) $financial->driver_earning)->toBe('50.00')
        ->and((string) $financial->platform_earning)->toBe('0.00');
});

test('launch fifty discount orders still apply driver commission', function () {
    $admin = User::factory()->systemAdmin()->create();
    $user = User::factory()->driver()->create();
    $driver = Driver::factory()->forUser($user)->approved($admin)->withCommission(10)->create();

    $order = Order::factory()->create([
        'assigned_driver_id' => $driver->id,
        'order_status' => OrderStatus::Delivered,
        'delivered_at' => now(),
        'service_fee' => 50,
        'service_fee_discount' => 25,
        'loyalty_reward_type' => LoyaltyRewardType::LaunchFifty,
    ]);

    OrderFinancial::factory()->create([
        'order_id' => $order->id,
        'service_fee' => 50,
        'driver_earning' => 50,
        'platform_earning' => 0,
        'driver_commission' => 0,
    ]);

    app(DriverCommissionService::class)->applyOnDelivery($order->fresh('financial'), $driver);
    $financial = $order->fresh('financial')->financial;

    expect((string) $financial->driver_commission)->toBe('10.00')
        ->and((string) $financial->driver_earning)->toBe('40.00')
        ->and((string) $financial->platform_earning)->toBe('10.00');
});

test('driver with unpaid previous-day commission is blocked from accepting', function () {
    $admin = User::factory()->systemAdmin()->create();
    $user = User::factory()->driver()->create();
    $driver = Driver::factory()
        ->forUser($user)
        ->approved($admin)
        ->withCommission(10)
        ->create([
            'availability_status' => DriverAvailabilityStatus::Available,
        ]);

    $oldOrder = Order::factory()->create([
        'assigned_driver_id' => $driver->id,
        'order_status' => OrderStatus::Delivered,
        'delivered_at' => Carbon::yesterday()->setTime(18, 0),
        'service_fee' => 50,
    ]);

    OrderFinancial::factory()->create([
        'order_id' => $oldOrder->id,
        'driver_earning' => 40,
        'driver_commission' => 10,
        'platform_earning' => 10,
        'commission_settled_at' => null,
    ]);

    $service = app(DriverCommissionService::class);

    expect($service->isBlockedFromAccepting($driver))->toBeTrue()
        ->and(fn () => $service->assertCanAcceptOrders($driver))
        ->toThrow(ValidationException::class);
});

test('admin marking commission paid unblocks the driver', function () {
    $admin = User::factory()->systemAdmin()->create();
    $user = User::factory()->driver()->create();
    $driver = Driver::factory()
        ->forUser($user)
        ->approved($admin)
        ->withCommission(5)
        ->create([
            'availability_status' => DriverAvailabilityStatus::Available,
        ]);

    $oldOrder = Order::factory()->create([
        'assigned_driver_id' => $driver->id,
        'order_status' => OrderStatus::Delivered,
        'delivered_at' => Carbon::yesterday()->setTime(12, 0),
    ]);

    OrderFinancial::factory()->create([
        'order_id' => $oldOrder->id,
        'driver_earning' => 45,
        'driver_commission' => 5,
        'commission_settled_at' => null,
    ]);

    $service = app(DriverCommissionService::class);

    expect($service->isBlockedFromAccepting($driver))->toBeTrue()
        ->and($service->unpaidAmount($driver))->toBe('5.00');

    $this->actingAs($admin)
        ->post(route('admin.drivers.commission.mark-paid', $driver))
        ->assertRedirect();

    expect($service->isBlockedFromAccepting($driver->fresh()))->toBeFalse()
        ->and($service->unpaidAmount($driver->fresh()))->toBe('0.00')
        ->and($oldOrder->fresh('financial')->financial?->commission_settled_at)->not->toBeNull();
});

test('driver earnings page shows commission breakdown and owed card', function () {
    $admin = User::factory()->systemAdmin()->create();
    $user = User::factory()->driver()->create([
        'email_verified_at' => now(),
        'must_change_password' => false,
    ]);
    $driver = Driver::factory()->forUser($user)->approved($admin)->withCommission(10)->create();

    $order = Order::factory()->create([
        'assigned_driver_id' => $driver->id,
        'order_status' => OrderStatus::Delivered,
        'delivered_at' => now()->subHour(),
    ]);

    OrderFinancial::factory()->create([
        'order_id' => $order->id,
        'driver_earning' => 40,
        'driver_commission' => 10,
    ]);

    $this->actingAs($user)
        ->get(route('driver.earnings.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('driver/earnings/index')
            ->where('summary.owed_to_chisdrive', '0.00')
            ->where('orders.0.driver_earning', '40.00')
            ->where('orders.0.chisdrive_share', '10.00')
            ->where('orders.0.gross_earning', '50.00'));
});

test('driver shared props include commission debt when blocked', function () {
    $admin = User::factory()->systemAdmin()->create();
    $user = User::factory()->driver()->create([
        'email_verified_at' => now(),
        'must_change_password' => false,
    ]);
    $driver = Driver::factory()->forUser($user)->approved($admin)->withCommission(10)->create();

    $oldOrder = Order::factory()->create([
        'assigned_driver_id' => $driver->id,
        'order_status' => OrderStatus::Delivered,
        'delivered_at' => Carbon::yesterday()->setTime(10, 0),
    ]);

    OrderFinancial::factory()->create([
        'order_id' => $oldOrder->id,
        'driver_earning' => 40,
        'driver_commission' => 10,
    ]);

    $this->actingAs($user)
        ->get(route('driver.home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('driver.commissionDebt.blocked', true)
            ->where('driver.commissionDebt.amount', '10.00'));
});
