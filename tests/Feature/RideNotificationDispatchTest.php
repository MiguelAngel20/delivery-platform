<?php

use App\Enums\BusinessDeliveryMode;
use App\Enums\BusinessOperationMode;
use App\Enums\BusinessStatus;
use App\Enums\BusinessUserRole;
use App\Enums\BusinessUserStatus;
use App\Enums\CancelledByType;
use App\Enums\DriverApprovalStatus;
use App\Enums\DriverAvailabilityStatus;
use App\Enums\DriverScope;
use App\Enums\OrderStatus;
use App\Enums\UpgradeRequestStatus;
use App\Enums\UpgradeRequestType;
use App\Jobs\Notifications\SendDriverRatingPromptJob;
use App\Models\Business;
use App\Models\BusinessBranch;
use App\Models\BusinessUpgradeRequest;
use App\Models\BusinessUser;
use App\Models\Customer;
use App\Models\CustomOrderRequest;
use App\Models\Driver;
use App\Models\DriverRating;
use App\Models\Order;
use App\Models\OrderCancellation;
use App\Models\User;
use App\Notifications\Business\AdminUpgradeRequestNotification;
use App\Notifications\CustomOrders\CustomQuoteReadyNotification;
use App\Notifications\CustomOrders\CustomRequestCreatedNotification;
use App\Notifications\Orders\AdminAffiliateOrderNotification;
use App\Notifications\Orders\DriverOrderReadyNotification;
use App\Notifications\Orders\DriverRatingPromptNotification;
use App\Notifications\Orders\DriverWasRatedNotification;
use App\Notifications\Orders\NewBusinessOrderNotification;
use App\Notifications\Orders\NewDriverOfferNotification;
use App\Notifications\Orders\OrderCancelledNotification;
use App\Notifications\Orders\OrderDriverAssignedNotification;
use App\Notifications\Orders\OrderStatusChangedNotification;
use App\Notifications\Orders\PlatformOrderPendingNotification;
use App\Services\Notifications\RideNotificationDispatcher;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

test('order accepted notifies customer with estimated time', function () {
    Notification::fake();

    $customerUser = User::factory()->customer()->create();
    $customer = Customer::factory()->for($customerUser)->create();
    $order = Order::factory()->create([
        'customer_id' => $customer->id,
        'order_status' => OrderStatus::Preparing,
        'estimated_preparation_minutes' => 15,
    ]);

    app(RideNotificationDispatcher::class)->statusChanged($order, OrderStatus::PendingBusiness);

    Notification::assertSentTo(
        $customerUser,
        OrderStatusChangedNotification::class,
        fn (OrderStatusChangedNotification $n): bool => $n->status === OrderStatus::Preparing
            && $n->title() === 'Tu pedido fue aceptado'
            && $n->body() === 'Tiempo estimado: 15 minutos.',
    );
});

test('DriverAssigned notifies Customer with OrderDriverAssignedNotification', function () {
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

    Notification::assertSentTo($customerUser, OrderDriverAssignedNotification::class);
    Notification::assertNotSentTo($customerUser, OrderStatusChangedNotification::class);
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

test('order status change stores customer inbox notification immediately', function () {
    Queue::fake();

    $customerUser = User::factory()->customer()->create();
    $customer = Customer::factory()->for($customerUser)->create();
    $order = Order::factory()->create([
        'customer_id' => $customer->id,
        'order_status' => OrderStatus::Accepted,
    ]);

    app(RideNotificationDispatcher::class)->statusChanged($order, OrderStatus::PendingBusiness);

    expect($customerUser->fresh()->notifications)->toHaveCount(1)
        ->and($customerUser->notifications->first()?->data['title'])->toBe('Tu pedido fue aceptado');
});

test('order created does not notify customer', function () {
    Notification::fake();

    $customerUser = User::factory()->customer()->create();
    $customer = Customer::factory()->for($customerUser)->create();
    $order = Order::factory()->create([
        'customer_id' => $customer->id,
        'order_status' => OrderStatus::PendingBusiness,
    ]);

    app(RideNotificationDispatcher::class)->orderCreated($order);

    Notification::assertNotSentTo($customerUser, OrderStatusChangedNotification::class);
});

test('ready for pickup does not notify customer', function () {
    Notification::fake();

    $customerUser = User::factory()->customer()->create();
    $customer = Customer::factory()->for($customerUser)->create();
    $order = Order::factory()->create([
        'customer_id' => $customer->id,
        'order_status' => OrderStatus::ReadyForPickup,
    ]);

    app(RideNotificationDispatcher::class)->statusChanged($order, OrderStatus::Preparing);

    Notification::assertNotSentTo($customerUser, OrderStatusChangedNotification::class);
});

test('picked up notifies customer that the order is on the way', function () {
    Notification::fake();

    $customerUser = User::factory()->customer()->create();
    $customer = Customer::factory()->for($customerUser)->create();
    $order = Order::factory()->create([
        'customer_id' => $customer->id,
        'order_status' => OrderStatus::PickedUp,
    ]);

    app(RideNotificationDispatcher::class)->statusChanged($order, OrderStatus::ReadyForPickup);

    Notification::assertSentTo(
        $customerUser,
        OrderStatusChangedNotification::class,
        fn (OrderStatusChangedNotification $n): bool => $n->status === OrderStatus::PickedUp
            && $n->title() === 'Tu pedido va en camino',
    );
});

test('on the way after pickup does not notify customer again', function () {
    Notification::fake();

    $customerUser = User::factory()->customer()->create();
    $customer = Customer::factory()->for($customerUser)->create();
    $order = Order::factory()->create([
        'customer_id' => $customer->id,
        'order_status' => OrderStatus::OnTheWay,
    ]);

    app(RideNotificationDispatcher::class)->statusChanged($order, OrderStatus::PickedUp);

    Notification::assertNotSentTo($customerUser, OrderStatusChangedNotification::class);
});

test('employee of the same branch is notified of a new order', function () {
    Notification::fake();

    $business = Business::factory()->create([
        'operation_mode' => BusinessOperationMode::Partner,
    ]);
    $branch = BusinessBranch::factory()->for($business)->create();
    $employee = User::factory()->businessEmployee()->create();
    BusinessUser::query()->create([
        'business_id' => $business->id,
        'user_id' => $employee->id,
        'role' => BusinessUserRole::BusinessEmployee,
        'status' => BusinessUserStatus::Active,
    ])->branches()->sync([$branch->id]);

    $order = Order::factory()->create([
        'branch_id' => $branch->id,
        'operation_mode' => BusinessOperationMode::Partner,
    ]);
    $order->setRelation('branch', $branch->load('business'));

    app(RideNotificationDispatcher::class)->orderCreated($order);

    Notification::assertSentTo($employee, NewBusinessOrderNotification::class);
});

test('partner order with ride drivers notifies system admin', function () {
    Notification::fake();

    $admin = User::factory()->systemAdmin()->create();
    $business = Business::factory()->create([
        'operation_mode' => BusinessOperationMode::Partner,
        'delivery_mode' => BusinessDeliveryMode::Hybrid,
    ]);
    $branch = BusinessBranch::factory()->for($business)->create();
    $order = Order::factory()->create([
        'branch_id' => $branch->id,
        'operation_mode' => BusinessOperationMode::Partner,
    ]);
    $order->setRelation('branch', $branch->load('business'));

    app(RideNotificationDispatcher::class)->orderCreated($order);

    Notification::assertSentTo($admin, AdminAffiliateOrderNotification::class);
});

test('partner order with own drivers does not notify system admin', function () {
    Notification::fake();

    $admin = User::factory()->systemAdmin()->create();
    $business = Business::factory()->create([
        'operation_mode' => BusinessOperationMode::Partner,
        'delivery_mode' => BusinessDeliveryMode::OwnDrivers,
    ]);
    $branch = BusinessBranch::factory()->for($business)->create();
    $order = Order::factory()->create([
        'branch_id' => $branch->id,
        'operation_mode' => BusinessOperationMode::Partner,
    ]);
    $order->setRelation('branch', $branch->load('business'));

    app(RideNotificationDispatcher::class)->orderCreated($order);

    Notification::assertNotSentTo($admin, AdminAffiliateOrderNotification::class);
    Notification::assertNotSentTo($admin, PlatformOrderPendingNotification::class);
});

test('customer cancellation notifies business and admin', function () {
    Notification::fake();

    $admin = User::factory()->systemAdmin()->create();
    $business = Business::factory()->create([
        'operation_mode' => BusinessOperationMode::Partner,
    ]);
    $branch = BusinessBranch::factory()->for($business)->create();
    $businessAdmin = User::factory()->businessAdmin()->create();
    BusinessUser::query()->create([
        'business_id' => $business->id,
        'user_id' => $businessAdmin->id,
        'role' => BusinessUserRole::BusinessAdmin,
        'status' => BusinessUserStatus::Active,
    ])->branches()->sync([$branch->id]);

    $order = Order::factory()->create([
        'branch_id' => $branch->id,
        'operation_mode' => BusinessOperationMode::Partner,
        'order_status' => OrderStatus::Cancelled,
    ]);
    $order->setRelation('branch', $branch->load('business'));
    $cancellation = OrderCancellation::factory()->create([
        'order_id' => $order->id,
        'cancelled_by_type' => CancelledByType::Customer,
    ]);
    $order->setRelation('cancellation', $cancellation);

    app(RideNotificationDispatcher::class)->statusChanged($order, OrderStatus::Preparing);

    Notification::assertSentTo($businessAdmin, OrderCancelledNotification::class);
    Notification::assertSentTo($admin, OrderCancelledNotification::class);
});

test('business cancellation does not notify the business', function () {
    Notification::fake();

    $business = Business::factory()->create([
        'operation_mode' => BusinessOperationMode::Partner,
    ]);
    $branch = BusinessBranch::factory()->for($business)->create();
    $businessAdmin = User::factory()->businessAdmin()->create();
    BusinessUser::query()->create([
        'business_id' => $business->id,
        'user_id' => $businessAdmin->id,
        'role' => BusinessUserRole::BusinessAdmin,
        'status' => BusinessUserStatus::Active,
    ])->branches()->sync([$branch->id]);

    $order = Order::factory()->create([
        'branch_id' => $branch->id,
        'operation_mode' => BusinessOperationMode::Partner,
        'order_status' => OrderStatus::Cancelled,
    ]);
    $order->setRelation('branch', $branch->load('business'));
    $cancellation = OrderCancellation::factory()->create([
        'order_id' => $order->id,
        'cancelled_by_type' => CancelledByType::Business,
    ]);
    $order->setRelation('cancellation', $cancellation);

    app(RideNotificationDispatcher::class)->statusChanged($order, OrderStatus::Preparing);

    Notification::assertNotSentTo($businessAdmin, OrderCancelledNotification::class);
});

test('assigned driver is notified when the order is ready', function () {
    Notification::fake();

    $driverUser = User::factory()->driver()->create();
    $driver = Driver::factory()->create([
        'user_id' => $driverUser->id,
        'approval_status' => DriverApprovalStatus::Approved,
    ]);
    $order = Order::factory()->create([
        'assigned_driver_id' => $driver->id,
        'order_status' => OrderStatus::ReadyForPickup,
        'merchant_name_snapshot' => 'Tacos Pepe',
    ]);
    $order->setRelation('assignedDriver', $driver->load('user'));

    app(RideNotificationDispatcher::class)->statusChanged($order, OrderStatus::Preparing);

    Notification::assertSentTo($driverUser, DriverOrderReadyNotification::class);
});

test('driver offer includes restaurant and wait time', function () {
    Notification::fake();

    $order = Order::factory()->create([
        'merchant_name_snapshot' => 'Tacos Pepe',
        'estimated_preparation_minutes' => 20,
    ]);
    $driverUser = User::factory()->driver()->create();
    $driver = Driver::factory()->create([
        'user_id' => $driverUser->id,
    ]);

    app(RideNotificationDispatcher::class)->driverOffer($order, $driver);

    Notification::assertSentTo(
        $driverUser,
        NewDriverOfferNotification::class,
        fn (NewDriverOfferNotification $n): bool => $n->body() === 'Nuevo pedido en Tacos Pepe en 20 minutos.',
    );
});

test('upgrade request notifies system admin', function () {
    Notification::fake();

    $admin = User::factory()->systemAdmin()->create();
    $business = Business::factory()->create();
    $upgrade = BusinessUpgradeRequest::query()->create([
        'business_id' => $business->id,
        'requested_by_user_id' => User::factory()->businessAdmin()->create()->id,
        'type' => UpgradeRequestType::AdditionalBranch,
        'requested_quantity' => 1,
        'status' => UpgradeRequestStatus::Pending,
    ]);

    app(RideNotificationDispatcher::class)->upgradeRequested($upgrade);

    Notification::assertSentTo($admin, AdminUpgradeRequestNotification::class);
});

test('driver rating notifies the driver', function () {
    Notification::fake();

    $driverUser = User::factory()->driver()->create();
    $driver = Driver::factory()->create(['user_id' => $driverUser->id]);
    $rating = DriverRating::factory()->create([
        'driver_id' => $driver->id,
        'overall_rating' => 5,
    ]);
    $rating->setRelation('driver', $driver->load('user'));

    app(RideNotificationDispatcher::class)->driverRated($rating);

    Notification::assertSentTo($driverUser, DriverWasRatedNotification::class);
});

test('rating prompt is skipped when the driver was already rated', function () {
    Notification::fake();

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

    Notification::assertNotSentTo($customerUser, DriverRatingPromptNotification::class);
});
