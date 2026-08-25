<?php

use App\Actions\Dispatch\AcceptDeliveryOrder;
use App\Enums\BusinessDeliveryMode;
use App\Enums\BusinessOperationMode;
use App\Enums\BusinessStatus;
use App\Enums\DriverAssignmentStatus;
use App\Enums\DriverAvailabilityStatus;
use App\Enums\DriverScope;
use App\Enums\FinancialPartyType;
use App\Enums\FinancialTransactionStatus;
use App\Enums\FinancialTransactionType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Business;
use App\Models\BusinessBranch;
use App\Models\Customer;
use App\Models\Driver;
use App\Models\DriverAssignment;
use App\Models\FinancialTransaction;
use App\Models\Order;
use App\Models\User;
use App\Services\Finance\OrderFinancialService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

function financeAssignSeedOrder(): Order
{
    $business = Business::factory()->create([
        'operation_mode' => BusinessOperationMode::Partner,
        'status' => BusinessStatus::Active,
        'delivery_mode' => BusinessDeliveryMode::Hybrid,
    ]);
    $branch = BusinessBranch::factory()->for($business)->create();
    $customer = Customer::factory()->create();

    return Order::factory()->create([
        'customer_id' => $customer->id,
        'branch_id' => $branch->id,
        'order_status' => OrderStatus::Preparing,
        'payment_method' => PaymentMethod::Cash,
        'assigned_driver_id' => null,
    ]);
}

function financeAssignSeedDriver(): array
{
    $user = User::factory()->driver()->create();
    $driver = Driver::factory()->approved()->forUser($user)->create([
        'driver_scope' => DriverScope::Platform,
        'availability_status' => DriverAvailabilityStatus::Available,
    ]);

    return compact('user', 'driver');
}

test('recording the same unique financial movement twice keeps one row', function () {
    $order = financeAssignSeedOrder();
    ['driver' => $driver] = financeAssignSeedDriver();
    $order->forceFill([
        'assigned_driver_id' => $driver->id,
        'order_status' => OrderStatus::PickedUp,
        'picked_up_at' => now(),
    ])->save();

    $service = app(OrderFinancialService::class);
    $service->createSnapshot($order);

    $first = $service->recordPickupPayment($order->fresh(['branch.business', 'financial']), $driver);
    $second = $service->recordPickupPayment($order->fresh(['branch.business', 'financial']), $driver);

    expect($first)->not->toBeNull()
        ->and($second?->id)->toBe($first->id)
        ->and(
            FinancialTransaction::query()
                ->where('order_id', $order->id)
                ->where('transaction_type', FinancialTransactionType::DriverToBusiness)
                ->count(),
        )->toBe(1);
});

test('non-adjustment financial types are unique per order at database level', function () {
    $order = financeAssignSeedOrder();

    FinancialTransaction::query()->create([
        'order_id' => $order->id,
        'from_party_type' => FinancialPartyType::Driver,
        'from_party_id' => 1,
        'to_party_type' => FinancialPartyType::Business,
        'to_party_id' => 1,
        'transaction_type' => FinancialTransactionType::DriverToBusiness,
        'amount' => '10.00',
        'payment_method' => PaymentMethod::Cash,
        'status' => FinancialTransactionStatus::Completed,
        'description' => 'first',
        'idempotency_key' => "order:{$order->id}:driver_to_business",
        'settled_at' => now(),
    ]);

    expect(fn () => FinancialTransaction::query()->create([
        'order_id' => $order->id,
        'from_party_type' => FinancialPartyType::Driver,
        'from_party_id' => 1,
        'to_party_type' => FinancialPartyType::Business,
        'to_party_id' => 1,
        'transaction_type' => FinancialTransactionType::DriverToBusiness,
        'amount' => '10.00',
        'payment_method' => PaymentMethod::Cash,
        'status' => FinancialTransactionStatus::Completed,
        'description' => 'duplicate',
        'idempotency_key' => "order:{$order->id}:driver_to_business:dup",
        'settled_at' => now(),
    ]))->toThrow(UniqueConstraintViolationException::class);
});

test('adjustment financial types may repeat for the same order', function () {
    $order = financeAssignSeedOrder();

    FinancialTransaction::query()->create([
        'order_id' => $order->id,
        'from_party_type' => FinancialPartyType::Platform,
        'from_party_id' => null,
        'to_party_type' => FinancialPartyType::Business,
        'to_party_id' => 1,
        'transaction_type' => FinancialTransactionType::Adjustment,
        'amount' => '5.00',
        'payment_method' => PaymentMethod::Cash,
        'status' => FinancialTransactionStatus::Completed,
        'description' => 'ajuste 1',
        'idempotency_key' => "order:{$order->id}:adjustment:1",
        'settled_at' => now(),
    ]);

    FinancialTransaction::query()->create([
        'order_id' => $order->id,
        'from_party_type' => FinancialPartyType::Platform,
        'from_party_id' => null,
        'to_party_type' => FinancialPartyType::Business,
        'to_party_id' => 1,
        'transaction_type' => FinancialTransactionType::Adjustment,
        'amount' => '3.00',
        'payment_method' => PaymentMethod::Cash,
        'status' => FinancialTransactionStatus::Completed,
        'description' => 'ajuste 2',
        'idempotency_key' => "order:{$order->id}:adjustment:2",
        'settled_at' => now(),
    ]);

    expect(
        FinancialTransaction::query()
            ->where('order_id', $order->id)
            ->where('transaction_type', FinancialTransactionType::Adjustment)
            ->count(),
    )->toBe(2);
});

test('second driver accept of the same order is rejected and leaves one accepted assignment', function () {
    $order = financeAssignSeedOrder();
    ['user' => $userA, 'driver' => $driverA] = financeAssignSeedDriver();
    ['user' => $userB, 'driver' => $driverB] = financeAssignSeedDriver();

    app(AcceptDeliveryOrder::class)->handle($order, $driverA, $userA);

    expect(fn () => app(AcceptDeliveryOrder::class)->handle($order->fresh(), $driverB, $userB))
        ->toThrow(ValidationException::class);

    expect($order->fresh()->assigned_driver_id)->toBe($driverA->id)
        ->and(
            DriverAssignment::query()
                ->where('order_id', $order->id)
                ->where('status', DriverAssignmentStatus::Accepted)
                ->count(),
        )->toBe(1);
});

test('accepted_order_slot unique blocks a second accepted assignment row', function () {
    $order = financeAssignSeedOrder();
    ['driver' => $driverA] = financeAssignSeedDriver();
    ['driver' => $driverB] = financeAssignSeedDriver();

    DriverAssignment::query()->create([
        'order_id' => $order->id,
        'driver_id' => $driverA->id,
        'status' => DriverAssignmentStatus::Accepted,
        'offered_at' => now(),
        'accepted_at' => now(),
    ]);

    expect(fn () => DriverAssignment::query()->create([
        'order_id' => $order->id,
        'driver_id' => $driverB->id,
        'status' => DriverAssignmentStatus::Accepted,
        'offered_at' => now(),
        'accepted_at' => now(),
    ]))->toThrow(UniqueConstraintViolationException::class);
});

test('cancelling accepted assignment frees the slot for another driver', function () {
    $order = financeAssignSeedOrder();
    ['driver' => $driverA] = financeAssignSeedDriver();
    ['user' => $userB, 'driver' => $driverB] = financeAssignSeedDriver();

    $assignment = DriverAssignment::query()->create([
        'order_id' => $order->id,
        'driver_id' => $driverA->id,
        'status' => DriverAssignmentStatus::Accepted,
        'offered_at' => now(),
        'accepted_at' => now(),
    ]);

    $assignment->forceFill([
        'status' => DriverAssignmentStatus::Cancelled,
        'cancelled_at' => now(),
    ])->save();

    $order->forceFill(['assigned_driver_id' => null])->save();

    app(AcceptDeliveryOrder::class)->handle($order->fresh(), $driverB, $userB);

    expect($order->fresh()->assigned_driver_id)->toBe($driverB->id)
        ->and(
            DriverAssignment::query()
                ->where('order_id', $order->id)
                ->where('status', DriverAssignmentStatus::Accepted)
                ->count(),
        )->toBe(1)
        ->and(
            DriverAssignment::query()
                ->where('order_id', $order->id)
                ->where('status', DriverAssignmentStatus::Cancelled)
                ->count(),
        )->toBe(1);
});

test('generated unique_order_type_key is populated for non-adjustment rows', function () {
    $order = financeAssignSeedOrder();

    $tx = FinancialTransaction::query()->create([
        'order_id' => $order->id,
        'from_party_type' => FinancialPartyType::Customer,
        'from_party_id' => $order->customer_id,
        'to_party_type' => FinancialPartyType::Driver,
        'to_party_id' => 1,
        'transaction_type' => FinancialTransactionType::CustomerToDriver,
        'amount' => '20.00',
        'payment_method' => PaymentMethod::Cash,
        'status' => FinancialTransactionStatus::Completed,
        'description' => 'cobro',
        'idempotency_key' => "order:{$order->id}:customer_to_driver",
        'settled_at' => now(),
    ]);

    $key = DB::table('financial_transactions')->where('id', $tx->id)->value('unique_order_type_key');

    expect($key)->toBe($order->id.':customer_to_driver');
});
