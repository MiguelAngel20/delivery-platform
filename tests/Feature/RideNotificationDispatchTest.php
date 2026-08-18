<?php

use App\Enums\BusinessOperationMode;
use App\Enums\BusinessStatus;
use App\Enums\BusinessUserRole;
use App\Enums\BusinessUserStatus;
use App\Enums\DriverApprovalStatus;
use App\Enums\DriverAvailabilityStatus;
use App\Enums\DriverScope;
use App\Enums\OrderStatus;
use App\Models\Business;
use App\Models\BusinessBranch;
use App\Models\BusinessUser;
use App\Models\Customer;
use App\Models\CustomOrderRequest;
use App\Models\Driver;
use App\Models\Order;
use App\Models\User;
use App\Notifications\CustomOrders\CustomQuoteReadyNotification;
use App\Notifications\CustomOrders\CustomRequestCreatedNotification;
use App\Notifications\Orders\NewBusinessOrderNotification;
use App\Notifications\Orders\NewDriverOfferNotification;
use App\Notifications\Orders\OrderStatusChangedNotification;
use App\Notifications\Orders\PlatformOrderPendingNotification;
use App\Services\Notifications\RideNotificationDispatcher;
use Illuminate\Support\Facades\Notification;

test('OrderAccepted creates Customer notification', function () {
    Notification::fake();

    $customerUser = User::factory()->customer()->create();
    $customer = Customer::factory()->for($customerUser)->create();
    $order = Order::factory()->create([
        'customer_id' => $customer->id,
        'order_status' => OrderStatus::Accepted,
    ]);

    app(RideNotificationDispatcher::class)->statusChanged($order, OrderStatus::PendingBusiness);

    Notification::assertSentTo(
        $customerUser,
        OrderStatusChangedNotification::class,
        fn (OrderStatusChangedNotification $n): bool => $n->status === OrderStatus::Accepted,
    );
});

test('DriverAssigned creates Customer notification', function () {
    Notification::fake();

    $customerUser = User::factory()->customer()->create();
    $customer = Customer::factory()->for($customerUser)->create();
    $order = Order::factory()->create([
        'customer_id' => $customer->id,
        'order_status' => OrderStatus::DriverAssigned,
    ]);

    app(RideNotificationDispatcher::class)->driverAssigned($order);

    Notification::assertSentTo($customerUser, OrderStatusChangedNotification::class);
});

test('OrderDelivered creates Customer notification', function () {
    Notification::fake();

    $customerUser = User::factory()->customer()->create();
    $customer = Customer::factory()->for($customerUser)->create();
    $order = Order::factory()->create([
        'customer_id' => $customer->id,
        'order_status' => OrderStatus::Delivered,
    ]);

    app(RideNotificationDispatcher::class)->statusChanged($order, OrderStatus::OnTheWay);

    Notification::assertSentTo(
        $customerUser,
        OrderStatusChangedNotification::class,
        fn (OrderStatusChangedNotification $n): bool => $n->status === OrderStatus::Delivered,
    );
});

test('new partner order notifies permitted Business users', function () {
    Notification::fake();

    $business = Business::factory()->create([
        'operation_mode' => BusinessOperationMode::Partner,
        'status' => BusinessStatus::Active,
    ]);
    $branch = BusinessBranch::factory()->for($business)->create();
    $admin = User::factory()->businessAdmin()->create();
    BusinessUser::query()->create([
        'business_id' => $business->id,
        'user_id' => $admin->id,
        'role' => BusinessUserRole::BusinessAdmin,
        'status' => BusinessUserStatus::Active,
    ])->branches()->sync([$branch->id]);

    $order = Order::factory()->create([
        'branch_id' => $branch->id,
        'operation_mode' => BusinessOperationMode::Partner,
        'order_status' => OrderStatus::PendingBusiness,
    ]);
    $order->setRelation('branch', $branch->load('business'));

    app(RideNotificationDispatcher::class)->orderCreated($order);

    Notification::assertSentTo($admin, NewBusinessOrderNotification::class);
});

test('employee from unrelated branch is not notified', function () {
    Notification::fake();

    $business = Business::factory()->create([
        'operation_mode' => BusinessOperationMode::Partner,
    ]);
    $branchA = BusinessBranch::factory()->for($business)->create();
    $branchB = BusinessBranch::factory()->for($business)->create();

    $employee = User::factory()->businessEmployee()->create();
    $membership = BusinessUser::query()->create([
        'business_id' => $business->id,
        'user_id' => $employee->id,
        'role' => BusinessUserRole::BusinessEmployee,
        'status' => BusinessUserStatus::Active,
    ]);
    $membership->branches()->attach($branchB->id);

    $order = Order::factory()->create([
        'branch_id' => $branchA->id,
        'operation_mode' => BusinessOperationMode::Partner,
    ]);
    $order->setRelation('branch', $branchA->load('business'));

    app(RideNotificationDispatcher::class)->orderCreated($order);

    Notification::assertNotSentTo($employee, NewBusinessOrderNotification::class);
});

test('eligible Driver receives offer and ineligible does not', function () {
    Notification::fake();

    $order = Order::factory()->create();
    $eligibleUser = User::factory()->driver()->create();
    $eligible = Driver::factory()->create([
        'user_id' => $eligibleUser->id,
        'approval_status' => DriverApprovalStatus::Approved,
        'availability_status' => DriverAvailabilityStatus::Available,
        'driver_scope' => DriverScope::Platform,
    ]);

    $ineligibleUser = User::factory()->driver()->create();
    Driver::factory()->create([
        'user_id' => $ineligibleUser->id,
        'approval_status' => DriverApprovalStatus::Approved,
        'availability_status' => DriverAvailabilityStatus::Offline,
        'driver_scope' => DriverScope::Platform,
    ]);

    $dispatcher = app(RideNotificationDispatcher::class);
    $dispatcher->driverOffer($order, $eligible);

    Notification::assertSentTo($eligibleUser, NewDriverOfferNotification::class);
    Notification::assertNotSentTo($ineligibleUser, NewDriverOfferNotification::class);
});

test('Custom Quote Ready notifies owning Customer only', function () {
    Notification::fake();

    $ownerUser = User::factory()->customer()->create();
    $owner = Customer::factory()->for($ownerUser)->create();
    $otherUser = User::factory()->customer()->create();
    Customer::factory()->for($otherUser)->create();

    $request = CustomOrderRequest::factory()->create([
        'customer_id' => $owner->id,
    ]);

    app(RideNotificationDispatcher::class)->customQuoteReady($request);

    Notification::assertSentTo($ownerUser, CustomQuoteReadyNotification::class);
    Notification::assertNotSentTo($otherUser, CustomQuoteReadyNotification::class);
});

test('platform order notifies system admin', function () {
    Notification::fake();

    $admin = User::factory()->systemAdmin()->create();
    $order = Order::factory()->create([
        'operation_mode' => BusinessOperationMode::PlatformOperated,
        'order_status' => OrderStatus::PendingPlatform,
        'branch_id' => null,
    ]);

    app(RideNotificationDispatcher::class)->orderCreated($order);

    Notification::assertSentTo($admin, PlatformOrderPendingNotification::class);
});

test('custom request notifies system admin', function () {
    Notification::fake();

    $admin = User::factory()->systemAdmin()->create();
    $request = CustomOrderRequest::factory()->create();

    app(RideNotificationDispatcher::class)->customOrderRequested($request);

    Notification::assertSentTo($admin, CustomRequestCreatedNotification::class);
});
