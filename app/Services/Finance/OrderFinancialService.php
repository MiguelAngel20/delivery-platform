<?php

namespace App\Services\Finance;

use App\Enums\CollectionParty;
use App\Enums\FinancialPartyType;
use App\Enums\FinancialTransactionStatus;
use App\Enums\FinancialTransactionType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\SettlementStatus;
use App\Models\Driver;
use App\Models\FinancialTransaction;
use App\Models\Order;
use App\Models\OrderFinancial;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class OrderFinancialService
{
    public function __construct(
        private readonly RevenueAllocationService $allocation,
    ) {}

    public function createSnapshot(Order $order): OrderFinancial
    {
        $existing = OrderFinancial::query()->where('order_id', $order->id)->first();

        if ($existing !== null) {
            $this->ensurePendingPayment($order);

            return $existing;
        }

        $allocated = $this->allocation->allocate($order);

        try {
            $financial = OrderFinancial::query()->create([
                'order_id' => $order->id,
                'products_amount' => $order->subtotal_before_discount,
                'discount_amount' => $order->discount_total,
                'service_fee' => $order->service_fee,
                'delivery_fee' => $order->delivery_fee,
                'customer_total' => $order->total,
                'business_amount' => $allocated['business_amount'],
                'driver_earning' => $allocated['driver_earning'],
                'platform_earning' => $allocated['platform_earning'],
                'payment_method' => $order->payment_method,
                'collection_party' => $allocated['collection_party'],
                'settlement_status' => SettlementStatus::Open,
            ]);
        } catch (UniqueConstraintViolationException) {
            $financial = OrderFinancial::query()
                ->where('order_id', $order->id)
                ->firstOrFail();
        }

        $this->ensurePendingPayment($order);

        return $financial;
    }

    public function refreshSnapshot(Order $order): OrderFinancial
    {
        $order->loadMissing(['items', 'financial', 'payment']);

        $allocated = $this->allocation->allocate($order);
        $financial = $order->financial ?? $this->createSnapshot($order->fresh(['items']));

        $financial->forceFill([
            'products_amount' => $order->subtotal_before_discount,
            'discount_amount' => $order->discount_total,
            'service_fee' => $order->service_fee,
            'delivery_fee' => $order->delivery_fee,
            'customer_total' => $order->total,
            'business_amount' => $allocated['business_amount'],
            'driver_earning' => $allocated['driver_earning'],
            'platform_earning' => $allocated['platform_earning'],
            'payment_method' => $order->payment_method,
            'collection_party' => $allocated['collection_party'],
        ])->save();

        $payment = $this->ensurePendingPayment($order);

        if ($payment->status === PaymentStatus::Pending) {
            $payment->forceFill([
                'amount' => $order->total,
                'payment_method' => $order->payment_method,
            ])->save();
        }

        return $financial->fresh();
    }

    public function ensurePendingPayment(Order $order): Payment
    {
        $payment = Payment::query()->where('order_id', $order->id)->first();

        if ($payment !== null) {
            return $payment;
        }

        try {
            return Payment::query()->create([
                'order_id' => $order->id,
                'payment_method' => $order->payment_method,
                'amount' => $order->total,
                'status' => PaymentStatus::Pending,
                'received_by_type' => null,
                'received_by_id' => null,
                'paid_at' => null,
            ]);
        } catch (UniqueConstraintViolationException) {
            return Payment::query()
                ->where('order_id', $order->id)
                ->firstOrFail();
        }
    }

    public function shouldDriverPayBusinessOnPickup(Order $order): bool
    {
        if ($order->payment_method !== PaymentMethod::Cash) {
            return false;
        }

        $financial = $order->financial ?? $this->createSnapshot($order);

        if ($financial->collection_party !== CollectionParty::Driver) {
            return false;
        }

        return (bool) config('business.finance.cash.driver_pays_business_on_pickup', true);
    }

    public function recordPickupPayment(Order $order, Driver $driver, ?User $actor = null): ?FinancialTransaction
    {
        $order->loadMissing(['branch.business', 'financial']);

        if (! $this->shouldDriverPayBusinessOnPickup($order)) {
            return null;
        }

        $financial = $order->financial ?? $this->createSnapshot($order);

        $toType = $order->branch?->business_id !== null
            ? FinancialPartyType::Business
            : FinancialPartyType::ExternalMerchant;
        $toId = $order->branch?->business_id;

        return $this->recordUniqueTransaction(
            order: $order,
            type: FinancialTransactionType::DriverToBusiness,
            amount: (string) $financial->business_amount,
            fromType: FinancialPartyType::Driver,
            fromId: $driver->id,
            toType: $toType,
            toId: $toId,
            actor: $actor,
            description: $toType === FinancialPartyType::ExternalMerchant
                ? 'Pago del repartidor al establecimiento externo al recoger'
                : 'Pago del repartidor al establecimiento al recoger',
        );
    }

    public function recordCustomerCollection(Order $order, Driver $driver, ?User $actor = null): ?FinancialTransaction
    {
        $order->loadMissing(['financial', 'payment']);

        $financial = $order->financial ?? $this->createSnapshot($order);

        if (
            $order->payment_method !== PaymentMethod::Cash
            || $financial->collection_party !== CollectionParty::Driver
        ) {
            return null;
        }

        $transaction = $this->recordUniqueTransaction(
            order: $order,
            type: FinancialTransactionType::CustomerToDriver,
            amount: (string) $financial->customer_total,
            fromType: FinancialPartyType::Customer,
            fromId: $order->customer_id,
            toType: FinancialPartyType::Driver,
            toId: $driver->id,
            actor: $actor,
            description: 'Cobro en efectivo del cliente al repartidor',
        );

        $this->markCashPaymentPaid($order, $driver);

        return $transaction;
    }

    /**
     * @return array{snapshot: bool, settlement: string, requires_review: bool}
     */
    public function backfillDeliveredOrder(Order $order): array
    {
        $order->loadMissing(['assignedDriver', 'branch.business', 'financial', 'payment']);

        $financial = $order->financial ?? $this->createSnapshot($order);
        $driver = $order->assignedDriver;
        $canReconstructCash = $order->payment_method === PaymentMethod::Cash
            && $driver !== null
            && $order->picked_up_at !== null
            && $order->delivered_at !== null;

        if ($canReconstructCash) {
            $this->recordPickupPayment($order->fresh(['branch.business', 'financial']), $driver);
            $this->recordCustomerCollection($order->fresh(['financial', 'payment', 'branch.business']), $driver);
            $financial = $this->recalculateSettlement($order->fresh(['financial', 'financialTransactions']));

            return [
                'snapshot' => true,
                'settlement' => $financial->settlement_status->value,
                'requires_review' => false,
            ];
        }

        $financial->forceFill([
            'settlement_status' => SettlementStatus::RequiresReview,
        ])->save();

        return [
            'snapshot' => true,
            'settlement' => SettlementStatus::RequiresReview->value,
            'requires_review' => true,
        ];
    }

    public function recalculateSettlement(Order $order): OrderFinancial
    {
        $financial = $order->financial ?? $this->createSnapshot($order);
        $order->loadMissing('financialTransactions');

        $completedTypes = $order->financialTransactions
            ->where('status', FinancialTransactionStatus::Completed)
            ->pluck('transaction_type')
            ->all();

        $required = [];

        if ($this->shouldDriverPayBusinessOnPickup($order)) {
            $required[] = FinancialTransactionType::DriverToBusiness;
        }

        if (
            $order->payment_method === PaymentMethod::Cash
            && $financial->collection_party === CollectionParty::Driver
        ) {
            $required[] = FinancialTransactionType::CustomerToDriver;
        }

        if ($required === []) {
            $status = SettlementStatus::Open;
        } else {
            $completedCount = collect($required)
                ->filter(fn (FinancialTransactionType $type): bool => in_array($type, $completedTypes, true))
                ->count();

            $status = match (true) {
                $completedCount === 0 => SettlementStatus::Open,
                $completedCount === count($required) => SettlementStatus::Settled,
                default => SettlementStatus::PartiallySettled,
            };
        }

        $financial->forceFill([
            'settlement_status' => $status,
        ])->save();

        return $financial->fresh();
    }

    private function markCashPaymentPaid(Order $order, Driver $driver): Payment
    {
        $payment = $this->ensurePendingPayment($order);

        if ($payment->status === PaymentStatus::Paid) {
            $this->syncOrderPaymentStatus($order, $payment);

            return $payment;
        }

        $updated = Payment::query()
            ->whereKey($payment->id)
            ->where('status', PaymentStatus::Pending)
            ->update([
                'status' => PaymentStatus::Paid,
                'received_by_type' => FinancialPartyType::Driver,
                'received_by_id' => $driver->id,
                'paid_at' => now(),
                'amount' => $order->total,
                'payment_method' => $order->payment_method,
            ]);

        $payment = Payment::query()->whereKey($payment->id)->firstOrFail();

        if ($updated === 1) {
            $this->syncOrderPaymentStatus($order, $payment);
        }

        return $payment;
    }

    private function syncOrderPaymentStatus(Order $order, Payment $payment): void
    {
        if ($order->payment_status === $payment->status) {
            return;
        }

        $order->forceFill([
            'payment_status' => $payment->status,
        ])->save();
    }

    private function recordUniqueTransaction(
        Order $order,
        FinancialTransactionType $type,
        string $amount,
        FinancialPartyType $fromType,
        ?int $fromId,
        FinancialPartyType $toType,
        ?int $toId,
        ?User $actor,
        string $description,
    ): FinancialTransaction {
        if (! $type->isUniquePerOrder()) {
            throw ValidationException::withMessages([
                'transaction_type' => 'Este tipo de movimiento no usa registro único por pedido.',
            ]);
        }

        if (bccomp($amount, '0.00', 2) === -1) {
            throw ValidationException::withMessages([
                'amount' => 'El monto financiero no puede ser negativo.',
            ]);
        }

        $idempotencyKey = "order:{$order->id}:{$type->value}";

        $existing = FinancialTransaction::query()
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existing !== null) {
            $this->recalculateSettlement($order->fresh(['financial', 'financialTransactions']));

            return $existing;
        }

        $existingByType = FinancialTransaction::query()
            ->where('order_id', $order->id)
            ->where('transaction_type', $type)
            ->where('status', FinancialTransactionStatus::Completed)
            ->first();

        if ($existingByType !== null) {
            $this->recalculateSettlement($order->fresh(['financial', 'financialTransactions']));

            return $existingByType;
        }

        try {
            $transaction = FinancialTransaction::query()->create([
                'order_id' => $order->id,
                'from_party_type' => $fromType,
                'from_party_id' => $fromId,
                'to_party_type' => $toType,
                'to_party_id' => $toId,
                'transaction_type' => $type,
                'amount' => $amount,
                'payment_method' => $order->payment_method,
                'status' => FinancialTransactionStatus::Completed,
                'description' => $description,
                'idempotency_key' => $idempotencyKey,
                'recorded_by_user_id' => $actor?->id,
                'settled_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            $existing = FinancialTransaction::query()
                ->where(function ($query) use ($idempotencyKey, $order, $type): void {
                    $query->where('idempotency_key', $idempotencyKey)
                        ->orWhere(function ($inner) use ($order, $type): void {
                            $inner->where('order_id', $order->id)
                                ->where('transaction_type', $type);
                        });
                })
                ->firstOrFail();

            $this->recalculateSettlement($order->fresh(['financial', 'financialTransactions']));

            return $existing;
        }

        $this->recalculateSettlement($order->fresh(['financial', 'financialTransactions']));

        return $transaction;
    }

    public function markRequiresReview(Order $order): ?OrderFinancial
    {
        $financial = $order->financial ?? OrderFinancial::query()->where('order_id', $order->id)->first();

        if ($financial === null) {
            return null;
        }

        $financial->forceFill([
            'settlement_status' => SettlementStatus::RequiresReview,
        ])->save();

        return $financial->fresh();
    }

    public function hasCompletedMovements(Order $order): bool
    {
        return FinancialTransaction::query()
            ->where('order_id', $order->id)
            ->where('status', FinancialTransactionStatus::Completed)
            ->exists();
    }

    /**
     * Run financial side-effects while already inside an outer DB transaction.
     */
    public function withinLockedOrder(Order $order, callable $callback): mixed
    {
        return DB::transaction(function () use ($order, $callback) {
            /** @var Order $locked */
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            return $callback($locked);
        });
    }
}
