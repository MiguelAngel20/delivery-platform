<?php

use App\Actions\Orders\AcceptBusinessOrder;
use App\Actions\Orders\CreateOrder;
use App\Enums\BusinessOperationMode;
use App\Enums\BusinessStatus;
use App\Enums\CancellationReasonCode;
use App\Enums\LoyaltyRewardType;
use App\Enums\OrderStatus;
use App\Models\Business;
use App\Models\BusinessBranch;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\CustomerLoyaltyAccount;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\User;
use App\Services\Loyalty\CustomerLoyaltyService;
use App\Services\Orders\OrderCancellationService;
use Illuminate\Support\Facades\Hash;

beforeEach(function (): void {
    config(['business.loyalty.launch.max_customers' => 25]);
});

function seedLoyaltyCustomer(): array
{
    $user = User::factory()->customer()->create([
        'password' => Hash::make('password'),
    ]);
    $customer = Customer::factory()->for($user)->create();
    $address = CustomerAddress::factory()->create([
        'customer_id' => $customer->id,
        'is_default' => true,
    ]);

    return compact('user', 'customer', 'address');
}

function seedLoyaltyCatalog(): array
{
    $business = Business::factory()->create([
        'operation_mode' => BusinessOperationMode::Partner,
        'status' => BusinessStatus::Active,
    ]);
    $branch = BusinessBranch::factory()->for($business)->create([
        'latitude' => 16.251,
        'longitude' => -92.134,
    ]);
    $product = Product::factory()->create([
        'branch_id' => $branch->id,
        'name' => 'Taco',
        'is_active' => true,
        'is_available' => true,
    ]);
    ProductPrice::factory()->create([
        'product_id' => $product->id,
        'list_price' => 100,
        'is_active' => true,
    ]);

    return compact('business', 'branch', 'product');
}

function loyaltyOrderPayload(BusinessBranch $branch, Product $product, CustomerAddress $address): array
{
    return [
        'branch_id' => $branch->id,
        'items' => [[
            'product_id' => $product->id,
            'quantity' => 1,
            'selected_options' => [],
        ]],
        'delivery' => [
            'source' => 'saved_address',
            'customer_address_id' => $address->id,
        ],
    ];
}

function deliverLoyaltyOrder(Order $order): void
{
    $order->forceFill([
        'order_status' => OrderStatus::Delivered,
        'delivered_at' => now(),
    ])->save();

    app(CustomerLoyaltyService::class)->handleOrderDelivered($order->fresh());
}

test('first launch customers receive fifty percent service fee discount', function () {
    config(['business.loyalty.launch.max_customers' => 25]);

    ['user' => $user, 'customer' => $customer, 'address' => $address] = seedLoyaltyCustomer();
    ['branch' => $branch, 'product' => $product] = seedLoyaltyCatalog();

    $order = app(CreateOrder::class)->handle(
        $customer,
        $user,
        loyaltyOrderPayload($branch, $product, $address),
    );

    expect($order->loyalty_reward_type)->toBe(LoyaltyRewardType::LaunchFifty)
        ->and((string) $order->service_fee)->toBe('50.00')
        ->and((string) $order->service_fee_discount)->toBe('25.00')
        ->and((string) $order->total)->toBe('125.00');
});

test('launch discounted delivery does not increment streak counter', function () {
    ['user' => $user, 'customer' => $customer, 'address' => $address] = seedLoyaltyCustomer();
    ['branch' => $branch, 'product' => $product] = seedLoyaltyCatalog();

    $order = app(CreateOrder::class)->handle(
        $customer,
        $user,
        loyaltyOrderPayload($branch, $product, $address),
    );

    deliverLoyaltyOrder($order);

    $account = CustomerLoyaltyAccount::query()->where('customer_id', $customer->id)->first();

    expect($account)->not->toBeNull()
        ->and($account?->qualifying_orders_count)->toBe(0)
        ->and($account?->launch_consumed_at)->not->toBeNull();
});

test('five delivered orders unlock a pending streak reward between 15 and 20', function () {
    config([
        'business.loyalty.launch.max_customers' => 0,
        'business.loyalty.streak.discount_min' => 15,
        'business.loyalty.streak.discount_max' => 20,
    ]);

    ['user' => $user, 'customer' => $customer, 'address' => $address] = seedLoyaltyCustomer();
    ['branch' => $branch, 'product' => $product] = seedLoyaltyCatalog();

    for ($i = 0; $i < 5; $i++) {
        $order = app(CreateOrder::class)->handle(
            $customer,
            $user,
            loyaltyOrderPayload($branch, $product, $address),
        );
        deliverLoyaltyOrder($order);
    }

    $account = CustomerLoyaltyAccount::query()->where('customer_id', $customer->id)->firstOrFail();

    expect($account->qualifying_orders_count)->toBe(5)
        ->and($account->pending_reward_amount)->not->toBeNull()
        ->and((float) $account->pending_reward_amount)->toBeGreaterThanOrEqual(15)
        ->and((float) $account->pending_reward_amount)->toBeLessThanOrEqual(20);
});

test('sixth order applies pending streak reward and delivery resets the cycle', function () {
    config(['business.loyalty.launch.max_customers' => 0]);

    ['user' => $user, 'customer' => $customer, 'address' => $address] = seedLoyaltyCustomer();
    ['branch' => $branch, 'product' => $product] = seedLoyaltyCatalog();

    CustomerLoyaltyAccount::query()->create([
        'customer_id' => $customer->id,
        'qualifying_orders_count' => 5,
        'pending_reward_amount' => '20.00',
        'pending_reward_calculator' => 'UnlockRangeLoyaltyDiscountCalculator',
    ]);

    $order = app(CreateOrder::class)->handle(
        $customer,
        $user,
        loyaltyOrderPayload($branch, $product, $address),
    );

    expect($order->loyalty_reward_type)->toBe(LoyaltyRewardType::StreakReward)
        ->and((string) $order->service_fee_discount)->toBe('20.00')
        ->and((string) $order->total)->toBe('130.00');

    deliverLoyaltyOrder($order);

    $account = CustomerLoyaltyAccount::query()->where('customer_id', $customer->id)->firstOrFail();

    expect($account->qualifying_orders_count)->toBe(0)
        ->and($account->pending_reward_amount)->toBeNull()
        ->and($account->reserved_order_id)->toBeNull();
});

test('cancelling a streak reward order after accept burns the reward and resets cycle', function () {
    config(['business.loyalty.launch.max_customers' => 0]);

    ['user' => $user, 'customer' => $customer, 'address' => $address] = seedLoyaltyCustomer();
    ['branch' => $branch, 'product' => $product] = seedLoyaltyCatalog();

    CustomerLoyaltyAccount::query()->create([
        'customer_id' => $customer->id,
        'qualifying_orders_count' => 5,
        'pending_reward_amount' => '18.00',
    ]);

    $order = app(CreateOrder::class)->handle(
        $customer,
        $user,
        loyaltyOrderPayload($branch, $product, $address),
    );

    app(AcceptBusinessOrder::class)->handle($order->fresh(), $user, 20);

    app(OrderCancellationService::class)->cancelByCustomer(
        $order->fresh(),
        $user,
        CancellationReasonCode::CustomerChangedMind,
        'Ya no lo quiero',
    );

    $account = CustomerLoyaltyAccount::query()->where('customer_id', $customer->id)->firstOrFail();

    expect($account->qualifying_orders_count)->toBe(0)
        ->and($account->pending_reward_amount)->toBeNull()
        ->and($account->reserved_order_id)->toBeNull();
});

test('cancelling a streak reward order before accept restores the pending reward', function () {
    config(['business.loyalty.launch.max_customers' => 0]);

    ['user' => $user, 'customer' => $customer, 'address' => $address] = seedLoyaltyCustomer();
    ['branch' => $branch, 'product' => $product] = seedLoyaltyCatalog();

    CustomerLoyaltyAccount::query()->create([
        'customer_id' => $customer->id,
        'qualifying_orders_count' => 5,
        'pending_reward_amount' => '18.00',
    ]);

    $order = app(CreateOrder::class)->handle(
        $customer,
        $user,
        loyaltyOrderPayload($branch, $product, $address),
    );

    app(OrderCancellationService::class)->cancelByCustomer(
        $order->fresh(),
        $user,
        CancellationReasonCode::CustomerChangedMind,
        'Me equivoqué',
    );

    $account = CustomerLoyaltyAccount::query()->where('customer_id', $customer->id)->firstOrFail();

    expect((string) $account->pending_reward_amount)->toBe('18.00')
        ->and($account->qualifying_orders_count)->toBe(5)
        ->and($account->reserved_order_id)->toBeNull();
});

test('customer profile exposes loyalty progress payload', function () {
    ['user' => $user, 'customer' => $customer] = seedLoyaltyCustomer();

    CustomerLoyaltyAccount::query()->create([
        'customer_id' => $customer->id,
        'qualifying_orders_count' => 2,
    ]);

    $this->actingAs($user)
        ->get(route('customer.profile.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('customer/profile/index')
            ->where('loyalty.qualifying_orders_count', 2)
            ->where('loyalty.required_orders', 5)
            ->has('loyalty.slots', 5));
});

test('customer orders index exposes loyalty progress payload', function () {
    ['user' => $user, 'customer' => $customer] = seedLoyaltyCustomer();

    CustomerLoyaltyAccount::query()->create([
        'customer_id' => $customer->id,
        'qualifying_orders_count' => 3,
    ]);

    $this->actingAs($user)
        ->get(route('customer.orders.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('customer/orders/index')
            ->where('loyalty.qualifying_orders_count', 3)
            ->where('loyalty.required_orders', 5)
            ->has('loyalty.slots', 5));
});

test('storefront shares loyalty progress for authenticated customers', function () {
    ['user' => $user, 'customer' => $customer] = seedLoyaltyCustomer();

    CustomerLoyaltyAccount::query()->create([
        'customer_id' => $customer->id,
        'qualifying_orders_count' => 1,
    ]);

    $this->actingAs($user)
        ->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('loyalty.qualifying_orders_count', 1)
            ->where('loyalty.required_orders', 5)
            ->has('loyalty.slots', 5));
});

test('loyalty progress helper reports launch availability for first order customers', function () {
    ['customer' => $customer] = seedLoyaltyCustomer();

    $progress = app(CustomerLoyaltyService::class)->progressFor($customer);

    expect($progress['launch_available'])->toBeTrue()
        ->and($progress['next_service_fee_discount'])->toBe('25.00')
        ->and($progress['service_fee_after_discount'])->toBe('25.00');
});

test('admin launch overview reports remaining slots and launch customers', function () {
    config(['business.loyalty.launch.max_customers' => 25]);

    ['customer' => $customerA] = seedLoyaltyCustomer();
    ['customer' => $customerB] = seedLoyaltyCustomer();

    $orderA = Order::factory()->create([
        'customer_id' => $customerA->id,
        'service_fee' => 50,
        'service_fee_discount' => 25,
        'loyalty_reward_type' => LoyaltyRewardType::LaunchFifty,
        'order_status' => OrderStatus::Delivered,
        'delivered_at' => now()->subHour(),
    ]);

    $orderB = Order::factory()->create([
        'customer_id' => $customerB->id,
        'service_fee' => 50,
        'service_fee_discount' => 25,
        'loyalty_reward_type' => LoyaltyRewardType::LaunchFifty,
        'order_status' => OrderStatus::Preparing,
    ]);

    CustomerLoyaltyAccount::query()->create([
        'customer_id' => $customerA->id,
        'launch_consumed_at' => now()->subHour(),
        'launch_order_id' => $orderA->id,
    ]);

    CustomerLoyaltyAccount::query()->create([
        'customer_id' => $customerB->id,
        'reserved_order_id' => $orderB->id,
        'reserved_reward_type' => LoyaltyRewardType::LaunchFifty,
        'reserved_reward_amount' => 25,
    ]);

    $overview = app(CustomerLoyaltyService::class)->adminLaunchOverview();

    expect($overview['max_customers'])->toBe(25)
        ->and($overview['consumed'])->toBe(1)
        ->and($overview['reserved'])->toBe(1)
        ->and($overview['used'])->toBe(2)
        ->and($overview['remaining'])->toBe(23)
        ->and($overview['customers'])->toHaveCount(2)
        ->and($overview['customers'][0]['customer_id'])->toBe($customerA->id)
        ->and($overview['customers'][0]['status'])->toBe('delivered')
        ->and($overview['customers'][1]['status'])->toBe('in_progress');

    $admin = User::factory()->systemAdmin()->create();

    $this->actingAs($admin)
        ->get(route('admin.home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/home')
            ->where('loyaltyLaunch.used', 2)
            ->where('loyaltyLaunch.remaining', 23)
            ->has('loyaltyLaunch.customers', 2));
});
