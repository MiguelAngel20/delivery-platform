<?php

use App\Actions\Dispatch\AcceptDeliveryOrder;
use App\Actions\Dispatch\MarkDriverArrived;
use App\Actions\Dispatch\PickupOrder;
use App\Actions\Orders\AcceptBusinessOrder;
use App\Actions\Orders\CreateOrder;
use App\Actions\Orders\MarkOrderReady;
use App\Enums\BusinessOperationMode;
use App\Enums\BusinessStatus;
use App\Enums\BusinessUserRole;
use App\Enums\BusinessUserStatus;
use App\Enums\CancellationReasonCode;
use App\Enums\CancellationResponsibility;
use App\Enums\CancellationReviewStatus;
use App\Enums\CancelledByType;
use App\Enums\DriverAvailabilityStatus;
use App\Enums\DriverScope;
use App\Enums\FinancialTransactionType;
use App\Enums\OrderStatus;
use App\Enums\SettlementStatus;
use App\Models\Business;
use App\Models\BusinessBranch;
use App\Models\BusinessUser;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Driver;
use App\Models\FinancialTransaction;
use App\Models\Incident;
use App\Models\Order;
use App\Models\OrderCancellation;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\User;
use App\Services\Orders\OrderCancellationService;
use Illuminate\Validation\ValidationException;

function cancellationCatalog(): array
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

function cancellationCustomer(): array
{
    $user = User::factory()->customer()->create();
    $customer = Customer::factory()->for($user)->create();
    $address = CustomerAddress::factory()->create([
        'customer_id' => $customer->id,
        'is_default' => true,
    ]);

    return compact('user', 'customer', 'address');
}

function cancellationBusinessAdmin(Business $business): User
{
    $admin = User::factory()->businessAdmin()->create();
    BusinessUser::query()->create([
        'business_id' => $business->id,
        'user_id' => $admin->id,
        'role' => BusinessUserRole::BusinessAdmin,
        'status' => BusinessUserStatus::Active,
    ]);

    return $admin;
}

function createCancellableOrder(): Order
{
    ['user' => $user, 'customer' => $customer, 'address' => $address] = cancellationCustomer();
    ['branch' => $branch, 'product' => $product] = cancellationCatalog();

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

function pickupCancellableOrder(Order $order): array
{
    $order->forceFill([
        'order_status' => OrderStatus::Preparing,
        'business_accepted_at' => now(),
        'preparation_started_at' => now(),
        'estimated_preparation_minutes' => 15,
    ])->save();

    $driverUser = User::factory()->driver()->create();
    $driver = Driver::factory()->approved()->forUser($driverUser)->create([
        'driver_scope' => DriverScope::Platform,
        'availability_status' => DriverAvailabilityStatus::Available,
    ]);

    app(AcceptDeliveryOrder::class)->handle($order->fresh(), $driver, $driverUser);
    app(MarkDriverArrived::class)->handle($order->fresh(), $driver, $driverUser);
    app(MarkOrderReady::class)->handle($order->fresh(), $driverUser);
    app(PickupOrder::class)->handle($order->fresh(), $driver, $driverUser);

    return [
        'order' => $order->fresh(['financial', 'financialTransactions', 'customer.user']),
        'driver' => $driver,
        'driverUser' => $driverUser,
    ];
}

test('customer can cancel pending order', function () {
    $order = createCancellableOrder();
    $customerUser = $order->customer->user;

    $this->actingAs($customerUser)
        ->post(route('customer.orders.cancel', $order), [
            'reason_code' => CancellationReasonCode::CustomerChangedMind->value,
        ])
        ->assertRedirect();

    $order->refresh();

    expect($order->order_status)->toBe(OrderStatus::Cancelled)
        ->and($order->cancellation)->not->toBeNull()
        ->and($order->cancellation->cancelled_by_type)->toBe(CancelledByType::Customer)
        ->and($order->cancellation->responsibility)->toBe(CancellationResponsibility::Customer)
        ->and($order->cancellation->review_status)->toBe(CancellationReviewStatus::NotRequired)
        ->and(Incident::query()->where('order_id', $order->id)->count())->toBe(0);
});

test('customer cannot freely cancel picked-up order', function () {
    ['order' => $order] = pickupCancellableOrder(createCancellableOrder());
    $customerUser = $order->customer->user;

    $this->actingAs($customerUser)
        ->post(route('customer.orders.cancel', $order), [
            'reason_code' => CancellationReasonCode::CustomerChangedMind->value,
        ])
        ->assertSessionHasErrors('order');

    expect($order->fresh()->order_status)->toBe(OrderStatus::PickedUp)
        ->and(OrderCancellation::query()->where('order_id', $order->id)->exists())->toBeFalse();
});

test('business rejection before acceptance uses REJECTED', function () {
    $order = createCancellableOrder();
    $admin = cancellationBusinessAdmin($order->branch->business);

    $this->actingAs($admin)
        ->post(route('business.orders.reject', $order), [
            'reason' => 'No hay personal esta noche',
        ])
        ->assertRedirect();

    $order->refresh();

    expect($order->order_status)->toBe(OrderStatus::Rejected)
        ->and(OrderCancellation::query()->where('order_id', $order->id)->exists())->toBeFalse();
});

test('business cancellation after acceptance uses CANCELLED', function () {
    $order = createCancellableOrder();
    $admin = cancellationBusinessAdmin($order->branch->business);

    app(AcceptBusinessOrder::class)->handle($order, $admin, 20);

    $this->actingAs($admin)
        ->post(route('business.orders.cancel', $order->fresh()), [
            'reason_code' => CancellationReasonCode::BusinessOutOfStock->value,
        ])
        ->assertRedirect();

    $order->refresh();

    expect($order->order_status)->toBe(OrderStatus::Cancelled)
        ->and($order->cancellation)->not->toBeNull()
        ->and($order->cancellation->cancelled_by_type)->toBe(CancelledByType::Business)
        ->and($order->cancellation->responsibility)->toBe(CancellationResponsibility::Business)
        ->and($order->cancellation->review_status)->toBe(CancellationReviewStatus::NotRequired);
});

test('cancellation creates status history', function () {
    $order = createCancellableOrder();
    $customerUser = $order->customer->user;

    $this->actingAs($customerUser)
        ->post(route('customer.orders.cancel', $order), [
            'reason_code' => CancellationReasonCode::CustomerDuplicateOrder->value,
            'reason' => 'Lo pedí dos veces',
        ])
        ->assertRedirect();

    $history = $order->fresh()->statusHistory->firstWhere('status', OrderStatus::Cancelled);

    expect($history)->not->toBeNull()
        ->and($history->changed_by_user_id)->toBe($customerUser->id)
        ->and($history->notes)->toBe('Lo pedí dos veces');
});

test('cancellation stores previous status', function () {
    $order = createCancellableOrder();
    $admin = cancellationBusinessAdmin($order->branch->business);
    app(AcceptBusinessOrder::class)->handle($order, $admin, 15);

    $this->actingAs($admin)
        ->post(route('business.orders.cancel', $order->fresh()), [
            'reason_code' => CancellationReasonCode::BusinessCannotPrepare->value,
        ])
        ->assertRedirect();

    expect($order->fresh()->cancellation->previous_order_status)->toBe(OrderStatus::Preparing);
});

test('early cancellation without movements does not create fake transactions', function () {
    $order = createCancellableOrder();
    $before = FinancialTransaction::query()->where('order_id', $order->id)->count();

    $this->actingAs($order->customer->user)
        ->post(route('customer.orders.cancel', $order), [
            'reason_code' => CancellationReasonCode::CustomerChangedMind->value,
        ])
        ->assertRedirect();

    expect(FinancialTransaction::query()->where('order_id', $order->id)->count())->toBe($before)
        ->and($order->fresh()->financial?->settlement_status)->not->toBe(SettlementStatus::RequiresReview);
});

test('post-pickup cancellation marks settlement requires review', function () {
    ['order' => $order] = pickupCancellableOrder(createCancellableOrder());
    $admin = User::factory()->systemAdmin()->create();

    $this->actingAs($admin)
        ->post(route('admin.orders.cancel', $order), [
            'reason_code' => CancellationReasonCode::SafetyIssue->value,
            'reason' => 'Incidente en ruta',
        ])
        ->assertRedirect();

    $order->refresh();

    expect($order->order_status)->toBe(OrderStatus::Cancelled)
        ->and($order->financial->settlement_status)->toBe(SettlementStatus::RequiresReview)
        ->and($order->cancellation->responsibility)->toBe(CancellationResponsibility::UnderReview)
        ->and($order->cancellation->review_status)->toBe(CancellationReviewStatus::Pending)
        ->and(Incident::query()->where('order_id', $order->id)->exists())->toBeTrue();
});

test('financial transactions are not deleted on cancellation', function () {
    ['order' => $order] = pickupCancellableOrder(createCancellableOrder());
    $txId = FinancialTransaction::query()
        ->where('order_id', $order->id)
        ->where('transaction_type', FinancialTransactionType::DriverToBusiness)
        ->value('id');

    expect($txId)->not->toBeNull();

    app(OrderCancellationService::class)->cancelByAdmin(
        $order,
        User::factory()->systemAdmin()->create(),
        CancellationReasonCode::Other,
        'Revisión operativa',
    );

    expect(FinancialTransaction::query()->whereKey($txId)->exists())->toBeTrue()
        ->and(FinancialTransaction::query()->where('order_id', $order->id)->count())->toBeGreaterThan(0);
});

test('business ready after customer cancel is rejected', function () {
    $order = createCancellableOrder();
    $admin = cancellationBusinessAdmin($order->branch->business);
    app(AcceptBusinessOrder::class)->handle($order, $admin, 15);

    $this->actingAs($order->customer->user)
        ->post(route('customer.orders.cancel', $order->fresh()), [
            'reason_code' => CancellationReasonCode::CustomerChangedMind->value,
        ])
        ->assertRedirect();

    expect(fn () => app(MarkOrderReady::class)->handle($order->fresh(), $admin))
        ->toThrow(ValidationException::class);

    expect($order->fresh()->order_status)->toBe(OrderStatus::Cancelled)
        ->and(OrderCancellation::query()->where('order_id', $order->id)->count())->toBe(1);
});
