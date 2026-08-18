<?php

use App\Actions\Dispatch\AcceptDeliveryOrder;
use App\Actions\Dispatch\DeliverOrder;
use App\Actions\Dispatch\MarkDriverArrived;
use App\Actions\Dispatch\PickupOrder;
use App\Actions\Dispatch\StartDelivery;
use App\Actions\Orders\CreateOrder;
use App\Actions\Orders\MarkOrderReady;
use App\Enums\BusinessOperationMode;
use App\Enums\BusinessStatus;
use App\Enums\BusinessUserRole;
use App\Enums\BusinessUserStatus;
use App\Enums\DriverAvailabilityStatus;
use App\Enums\DriverScope;
use App\Enums\FinancialTransactionType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\SettlementStatus;
use App\Models\Business;
use App\Models\BusinessBranch;
use App\Models\BusinessUser;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Driver;
use App\Models\FinancialTransaction;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\User;
use App\Services\Finance\OrderFinancialService;

function seedFinanceCatalog(): array
{
    $business = Business::factory()->create([
        'operation_mode' => BusinessOperationMode::Partner,
        'status' => BusinessStatus::Active,
    ]);
    $branch = BusinessBranch::factory()->for($business)->create();
    $product = Product::factory()->create([
        'branch_id' => $branch->id,
        'is_active' => true,
        'is_available' => true,
    ]);
    ProductPrice::factory()->create([
        'product_id' => $product->id,
        'list_price' => 200,
        'is_active' => true,
    ]);

    return compact('business', 'branch', 'product');
}

function seedFinanceCustomer(): array
{
    $user = User::factory()->customer()->create();
    $customer = Customer::factory()->for($user)->create();
    $address = CustomerAddress::factory()->create([
        'customer_id' => $customer->id,
        'is_default' => true,
    ]);

    return compact('user', 'customer', 'address');
}

function seedFinanceDriver(): array
{
    $user = User::factory()->driver()->create();
    $driver = Driver::factory()->approved()->forUser($user)->create([
        'driver_scope' => DriverScope::Platform,
        'availability_status' => DriverAvailabilityStatus::Available,
    ]);

    return compact('user', 'driver');
}

function createCashOrder(): Order
{
    ['user' => $user, 'customer' => $customer, 'address' => $address] = seedFinanceCustomer();
    ['branch' => $branch, 'product' => $product] = seedFinanceCatalog();

    config([
        'business.orders.service_fee' => 50,
        'business.orders.delivery_fee' => 0,
        'business.finance.allocation.driver_service_fee_share' => 1,
        'business.finance.allocation.platform_service_fee_share' => 0,
        'business.finance.cash.driver_pays_business_on_pickup' => true,
        'business.finance.cash.collection_party' => 'driver',
    ]);

    return app(CreateOrder::class)->handle($customer, $user, [
        'branch_id' => $branch->id,
        'items' => [
            [
                'product_id' => $product->id,
                'quantity' => 1,
                'selected_options' => [],
            ],
        ],
        'delivery' => [
            'source' => 'saved_address',
            'customer_address_id' => $address->id,
        ],
    ]);
}

function progressToReady(Order $order): Order
{
    $order->forceFill([
        'order_status' => OrderStatus::Preparing,
        'business_accepted_at' => now(),
        'preparation_started_at' => now(),
        'estimated_preparation_minutes' => 15,
    ])->save();

    return $order->fresh();
}

function assignAndArrive(Order $order, Driver $driver, User $driverUser): Order
{
    app(AcceptDeliveryOrder::class)->handle($order, $driver, $driverUser);
    app(MarkDriverArrived::class)->handle($order->fresh(), $driver, $driverUser);
    app(MarkOrderReady::class)->handle($order->fresh(), $driverUser);

    return $order->fresh();
}

test('cash delivered order creates financial snapshot', function () {
    $order = createCashOrder();

    expect($order->financial)->not->toBeNull()
        ->and((string) $order->financial->products_amount)->toBe('200.00')
        ->and((string) $order->financial->service_fee)->toBe('50.00')
        ->and((string) $order->financial->customer_total)->toBe('250.00')
        ->and((string) $order->financial->business_amount)->toBe('200.00')
        ->and((string) $order->financial->driver_earning)->toBe('50.00')
        ->and((string) $order->financial->platform_earning)->toBe('0.00')
        ->and($order->payment)->not->toBeNull()
        ->and($order->payment->status)->toBe(PaymentStatus::Pending);
});

test('driver pickup records driver-to-business transaction', function () {
    $order = progressToReady(createCashOrder());
    ['user' => $driverUser, 'driver' => $driver] = seedFinanceDriver();
    $order = assignAndArrive($order, $driver, $driverUser);

    app(PickupOrder::class)->handle($order->fresh(), $driver, $driverUser);

    $tx = FinancialTransaction::query()
        ->where('order_id', $order->id)
        ->where('transaction_type', FinancialTransactionType::DriverToBusiness)
        ->first();

    expect($tx)->not->toBeNull()
        ->and((string) $tx->amount)->toBe('200.00')
        ->and($order->fresh()->financial->settlement_status)->toBe(SettlementStatus::PartiallySettled);
});

test('cash delivery records customer-to-driver transaction', function () {
    $order = progressToReady(createCashOrder());
    ['user' => $driverUser, 'driver' => $driver] = seedFinanceDriver();
    $order = assignAndArrive($order, $driver, $driverUser);

    app(PickupOrder::class)->handle($order->fresh(), $driver, $driverUser);
    app(StartDelivery::class)->handle($order->fresh(), $driver, $driverUser);
    app(DeliverOrder::class)->handle($order->fresh(), $driver, $driverUser);

    $tx = FinancialTransaction::query()
        ->where('order_id', $order->id)
        ->where('transaction_type', FinancialTransactionType::CustomerToDriver)
        ->first();

    expect($tx)->not->toBeNull()
        ->and((string) $tx->amount)->toBe('250.00')
        ->and($order->fresh()->financial->settlement_status)->toBe(SettlementStatus::Settled);
});

test('delivered order marks cash payment paid', function () {
    $order = progressToReady(createCashOrder());
    ['user' => $driverUser, 'driver' => $driver] = seedFinanceDriver();
    $order = assignAndArrive($order, $driver, $driverUser);

    app(PickupOrder::class)->handle($order->fresh(), $driver, $driverUser);
    app(StartDelivery::class)->handle($order->fresh(), $driver, $driverUser);
    app(DeliverOrder::class)->handle($order->fresh(), $driver, $driverUser);

    $order = $order->fresh(['payment']);

    expect($order->payment_status)->toBe(PaymentStatus::Paid)
        ->and($order->payment?->status)->toBe(PaymentStatus::Paid)
        ->and($order->payment?->paid_at)->not->toBeNull();
});

test('duplicate delivery does not duplicate transaction', function () {
    $order = progressToReady(createCashOrder());
    ['user' => $driverUser, 'driver' => $driver] = seedFinanceDriver();
    $order = assignAndArrive($order, $driver, $driverUser);

    app(PickupOrder::class)->handle($order->fresh(), $driver, $driverUser);
    app(StartDelivery::class)->handle($order->fresh(), $driver, $driverUser);
    app(DeliverOrder::class)->handle($order->fresh(), $driver, $driverUser);

    app(OrderFinancialService::class)->recordCustomerCollection($order->fresh(), $driver, $driverUser);

    expect(
        FinancialTransaction::query()
            ->where('order_id', $order->id)
            ->where('transaction_type', FinancialTransactionType::CustomerToDriver)
            ->count(),
    )->toBe(1)
        ->and(Payment::query()->where('order_id', $order->id)->count())->toBe(1);
});

test('duplicate pickup does not duplicate transaction', function () {
    $order = progressToReady(createCashOrder());
    ['user' => $driverUser, 'driver' => $driver] = seedFinanceDriver();
    $order = assignAndArrive($order, $driver, $driverUser);

    app(PickupOrder::class)->handle($order->fresh(), $driver, $driverUser);
    app(OrderFinancialService::class)->recordPickupPayment($order->fresh(), $driver, $driverUser);

    expect(
        FinancialTransaction::query()
            ->where('order_id', $order->id)
            ->where('transaction_type', FinancialTransactionType::DriverToBusiness)
            ->count(),
    )->toBe(1);
});

test('driver earnings include only delivered orders', function () {
    $order = progressToReady(createCashOrder());
    ['user' => $driverUser, 'driver' => $driver] = seedFinanceDriver();
    $order = assignAndArrive($order, $driver, $driverUser);

    app(PickupOrder::class)->handle($order->fresh(), $driver, $driverUser);
    app(StartDelivery::class)->handle($order->fresh(), $driver, $driverUser);
    app(DeliverOrder::class)->handle($order->fresh(), $driver, $driverUser);

    $cancelled = createCashOrder();
    $cancelled->forceFill([
        'assigned_driver_id' => $driver->id,
        'order_status' => OrderStatus::Cancelled,
        'delivered_at' => null,
    ])->save();
    $cancelled->financial?->forceFill([
        'driver_earning' => 50,
    ])->save();

    $this->actingAs($driverUser)
        ->get(route('driver.earnings.index'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('driver/earnings/index')
            ->where('summary.completed_orders', 1)
            ->where('summary.today', '50.00'));
});

test('cancelled order does not count as normal earning', function () {
    ['user' => $driverUser, 'driver' => $driver] = seedFinanceDriver();
    $order = createCashOrder();
    $order->forceFill([
        'assigned_driver_id' => $driver->id,
        'order_status' => OrderStatus::Cancelled,
    ])->save();

    $this->actingAs($driverUser)
        ->get(route('driver.earnings.index'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->where('summary.completed_orders', 0)
            ->where('summary.today', '0.00'));
});

test('driver cannot see another drivers earnings', function () {
    $order = progressToReady(createCashOrder());
    ['user' => $driverUserA, 'driver' => $driverA] = seedFinanceDriver();
    ['user' => $driverUserB] = seedFinanceDriver();
    $order = assignAndArrive($order, $driverA, $driverUserA);

    app(PickupOrder::class)->handle($order->fresh(), $driverA, $driverUserA);
    app(StartDelivery::class)->handle($order->fresh(), $driverA, $driverUserA);
    app(DeliverOrder::class)->handle($order->fresh(), $driverA, $driverUserA);

    $this->actingAs($driverUserB)
        ->get(route('driver.earnings.index'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->where('summary.completed_orders', 0)
            ->has('orders', 0));
});

test('business admin can view own financial summary', function () {
    $order = progressToReady(createCashOrder());
    $business = $order->branch->business;
    $admin = User::factory()->businessAdmin()->create();
    $branchId = $business->branches()->orderBy('id')->value('id')
        ?? BusinessBranch::factory()->for($business)->create()->id;

    BusinessUser::query()->create([
        'business_id' => $business->id,
        'user_id' => $admin->id,
        'role' => BusinessUserRole::BusinessAdmin,
        'status' => BusinessUserStatus::Active,
    ])->branches()->sync([$branchId]);

    ['user' => $driverUser, 'driver' => $driver] = seedFinanceDriver();
    $order = assignAndArrive($order, $driver, $driverUser);
    app(PickupOrder::class)->handle($order->fresh(), $driver, $driverUser);
    app(StartDelivery::class)->handle($order->fresh(), $driver, $driverUser);
    app(DeliverOrder::class)->handle($order->fresh(), $driver, $driverUser);

    $this->actingAs($admin)
        ->get(route('business.finance.index'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('business/finance/index')
            ->where('summary.completed_orders', 1));
});

test('business employee cannot access finance', function () {
    ['business' => $business] = seedFinanceCatalog();
    $employee = User::factory()->businessEmployee()->create();
    BusinessUser::query()->create([
        'business_id' => $business->id,
        'user_id' => $employee->id,
        'role' => BusinessUserRole::BusinessEmployee,
        'status' => BusinessUserStatus::Active,
    ]);

    $this->actingAs($employee)
        ->get(route('business.finance.index'))
        ->assertForbidden();
});

test('business a cannot view business b finances', function () {
    $order = progressToReady(createCashOrder());
    ['user' => $driverUser, 'driver' => $driver] = seedFinanceDriver();
    $order = assignAndArrive($order, $driver, $driverUser);
    app(PickupOrder::class)->handle($order->fresh(), $driver, $driverUser);
    app(StartDelivery::class)->handle($order->fresh(), $driver, $driverUser);
    app(DeliverOrder::class)->handle($order->fresh(), $driver, $driverUser);

    ['business' => $otherBusiness] = seedFinanceCatalog();
    $otherAdmin = User::factory()->businessAdmin()->create();
    $otherBranch = BusinessBranch::factory()->for($otherBusiness)->create();

    BusinessUser::query()->create([
        'business_id' => $otherBusiness->id,
        'user_id' => $otherAdmin->id,
        'role' => BusinessUserRole::BusinessAdmin,
        'status' => BusinessUserStatus::Active,
    ])->branches()->sync([$otherBranch->id]);

    $this->actingAs($otherAdmin)
        ->get(route('business.finance.index'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page->where('summary.completed_orders', 0));
});

test('system admin can view global finance', function () {
    $order = progressToReady(createCashOrder());
    ['user' => $driverUser, 'driver' => $driver] = seedFinanceDriver();
    $order = assignAndArrive($order, $driver, $driverUser);
    app(PickupOrder::class)->handle($order->fresh(), $driver, $driverUser);
    app(StartDelivery::class)->handle($order->fresh(), $driver, $driverUser);
    app(DeliverOrder::class)->handle($order->fresh(), $driver, $driverUser);

    $admin = User::factory()->systemAdmin()->create();

    $this->actingAs($admin)
        ->get(route('admin.finance.index'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('admin/finance/index')
            ->where('summary.delivered_orders', 1));

    $this->actingAs($admin)
        ->get(route('admin.finance.show', $order))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('admin/finance/show')
            ->where('financial.customer_total', '250.00')
            ->has('financial.transactions', 2));
});

test('backfill creates snapshot for delivered orders without financials', function () {
    ['user' => $driverUser, 'driver' => $driver] = seedFinanceDriver();
    ['branch' => $branch] = seedFinanceCatalog();
    $customer = Customer::factory()->create();

    $order = Order::factory()->create([
        'customer_id' => $customer->id,
        'branch_id' => $branch->id,
        'assigned_driver_id' => $driver->id,
        'order_status' => OrderStatus::Delivered,
        'payment_status' => PaymentStatus::Pending,
        'subtotal_before_discount' => 200,
        'discount_total' => 0,
        'subtotal_after_discount' => 200,
        'service_fee' => 50,
        'delivery_fee' => 0,
        'total' => 250,
        'picked_up_at' => now()->subHour(),
        'delivered_at' => now(),
    ]);

    expect($order->financial)->toBeNull();

    $this->artisan('finance:backfill-order-financials')
        ->assertSuccessful();

    $order = $order->fresh(['financial', 'payment', 'financialTransactions']);

    expect($order->financial)->not->toBeNull()
        ->and((string) $order->financial->customer_total)->toBe('250.00')
        ->and((string) $order->financial->driver_earning)->toBe('50.00')
        ->and($order->financial->settlement_status)->toBe(SettlementStatus::Settled)
        ->and($order->payment_status)->toBe(PaymentStatus::Paid)
        ->and($order->financialTransactions)->toHaveCount(2);

    $this->actingAs($driverUser)
        ->get(route('driver.earnings.index'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page->where('summary.completed_orders', 1));
});
