<?php

namespace App\Services\Loyalty;

use App\Enums\LoyaltyRewardType;
use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\CustomerLoyaltyAccount;
use App\Models\Order;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class CustomerLoyaltyService
{
    public function __construct(
        private readonly LoyaltyDiscountCalculator $discountCalculator,
    ) {}

    public function accountFor(Customer $customer): CustomerLoyaltyAccount
    {
        return CustomerLoyaltyAccount::query()->firstOrCreate(
            ['customer_id' => $customer->id],
            ['qualifying_orders_count' => 0],
        );
    }

    /**
     * Preview of loyalty state for UI (profile / checkout).
     *
     * @return array{
     *     qualifying_orders_count: int,
     *     required_orders: int,
     *     slots: list<bool>,
     *     progress_ratio: float,
     *     reward_ready: bool,
     *     pending_reward_amount: string|null,
     *     next_service_fee_discount: string,
     *     next_discount_label: string|null,
     *     launch_available: bool,
     *     service_fee: string,
     *     service_fee_after_discount: string
     * }
     */
    public function progressFor(Customer $customer): array
    {
        $account = $this->accountFor($customer);
        $required = $this->requiredOrders();
        $count = min($required, max(0, $account->qualifying_orders_count));
        $serviceFee = $this->baseServiceFee();

        $discount = '0.00';
        $label = null;
        $launchAvailable = false;

        if ($this->canApplyLaunchDiscount($customer, $account)) {
            $discount = $this->launchDiscountAmount($serviceFee);
            $label = LoyaltyRewardType::LaunchFifty->label();
            $launchAvailable = true;
        } elseif ($account->hasPendingReward()) {
            $discount = number_format((float) $account->pending_reward_amount, 2, '.', '');
            $label = LoyaltyRewardType::StreakReward->label();
        }

        if (bccomp($discount, $serviceFee, 2) === 1) {
            $discount = $serviceFee;
        }

        $slots = [];
        for ($i = 0; $i < $required; $i++) {
            $slots[] = $i < $count;
        }

        return [
            'qualifying_orders_count' => $count,
            'required_orders' => $required,
            'slots' => $slots,
            'progress_ratio' => $required > 0 ? $count / $required : 0.0,
            'reward_ready' => $account->hasPendingReward(),
            'pending_reward_amount' => $account->pending_reward_amount !== null
                ? number_format((float) $account->pending_reward_amount, 2, '.', '')
                : null,
            'next_service_fee_discount' => $discount,
            'next_discount_label' => $label,
            'launch_available' => $launchAvailable,
            'service_fee' => $serviceFee,
            'service_fee_after_discount' => bcsub($serviceFee, $discount, 2),
        ];
    }

    /**
     * Admin dashboard snapshot for the launch 50% campaign (first N customers).
     *
     * @return array{
     *     max_customers: int,
     *     consumed: int,
     *     reserved: int,
     *     used: int,
     *     remaining: int,
     *     progress_ratio: float,
     *     service_fee_percent: float,
     *     customers: list<array{
     *         customer_id: int,
     *         name: string|null,
     *         email: string|null,
     *         phone: string|null,
     *         status: string,
     *         status_label: string,
     *         order_id: int|null,
     *         order_number: string|null,
     *         order_status: string|null,
     *         order_status_label: string|null,
     *         service_fee_discount: string|null,
     *         claimed_at: string|null
     *     }>
     * }
     */
    public function adminLaunchOverview(): array
    {
        $max = max(0, (int) config('business.loyalty.launch.max_customers', 25));
        $percent = (float) config('business.loyalty.launch.service_fee_percent', 50);

        $consumed = CustomerLoyaltyAccount::query()
            ->whereNotNull('launch_consumed_at')
            ->count();

        $reserved = CustomerLoyaltyAccount::query()
            ->where('reserved_reward_type', LoyaltyRewardType::LaunchFifty)
            ->whereNotNull('reserved_order_id')
            ->count();

        $used = $consumed + $reserved;
        $remaining = max(0, $max - $used);

        $customers = CustomerLoyaltyAccount::query()
            ->with([
                'customer.user',
                'launchOrder',
                'reservedOrder',
            ])
            ->where(function ($query): void {
                $query->whereNotNull('launch_consumed_at')
                    ->orWhere(function ($reservedQuery): void {
                        $reservedQuery
                            ->where('reserved_reward_type', LoyaltyRewardType::LaunchFifty)
                            ->whereNotNull('reserved_order_id');
                    });
            })
            ->get()
            ->map(function (CustomerLoyaltyAccount $account): array {
                $delivered = $account->launch_consumed_at !== null;
                $order = $delivered ? $account->launchOrder : $account->reservedOrder;
                $claimedAt = $delivered
                    ? $account->launch_consumed_at
                    : ($order?->created_at ?? $account->updated_at);

                return [
                    'customer_id' => $account->customer_id,
                    'name' => $account->customer?->user?->name,
                    'email' => $account->customer?->user?->email,
                    'phone' => $account->customer?->user?->phone,
                    'status' => $delivered ? 'delivered' : 'in_progress',
                    'status_label' => $delivered ? 'Entregado' : 'En curso',
                    'order_id' => $order?->id,
                    'order_number' => $order?->order_number,
                    'order_status' => $order?->order_status?->value,
                    'order_status_label' => $order?->order_status?->label(),
                    'service_fee_discount' => $order?->service_fee_discount !== null
                        ? number_format((float) $order->service_fee_discount, 2, '.', '')
                        : null,
                    'claimed_at' => $claimedAt?->toIso8601String(),
                    'claimed_at_sort' => $claimedAt?->getTimestamp() ?? PHP_INT_MAX,
                ];
            })
            ->sortBy('claimed_at_sort')
            ->values()
            ->take($max)
            ->map(function (array $row): array {
                unset($row['claimed_at_sort']);

                return $row;
            })
            ->all();

        return [
            'max_customers' => $max,
            'consumed' => $consumed,
            'reserved' => $reserved,
            'used' => $used,
            'remaining' => $remaining,
            'progress_ratio' => $max > 0 ? min(1, $used / $max) : 0.0,
            'service_fee_percent' => $percent,
            'customers' => $customers,
        ];
    }

    /**
     * Apply loyalty discount at order creation (inside an open DB transaction).
     *
     * @return array{service_fee_discount: string, loyalty_reward_type: LoyaltyRewardType|null}
     */
    public function applyToNewOrder(Customer $customer, Order $order, string $serviceFee): array
    {
        $account = CustomerLoyaltyAccount::query()
            ->where('customer_id', $customer->id)
            ->lockForUpdate()
            ->first();

        if ($account === null) {
            $account = CustomerLoyaltyAccount::query()->create([
                'customer_id' => $customer->id,
                'qualifying_orders_count' => 0,
            ]);

            $account = CustomerLoyaltyAccount::query()
                ->whereKey($account->id)
                ->lockForUpdate()
                ->firstOrFail();
        }

        if ($account->reserved_order_id !== null) {
            return [
                'service_fee_discount' => '0.00',
                'loyalty_reward_type' => null,
            ];
        }

        if ($this->canApplyLaunchDiscount($customer, $account)) {
            return Cache::lock('loyalty:launch-slots', 10)->block(5, function () use ($customer, $account, $order, $serviceFee): array {
                $account->refresh();

                if (! $this->canApplyLaunchDiscount($customer, $account)) {
                    return [
                        'service_fee_discount' => '0.00',
                        'loyalty_reward_type' => null,
                    ];
                }

                $discount = $this->launchDiscountAmount($serviceFee);

                $account->forceFill([
                    'reserved_order_id' => $order->id,
                    'reserved_reward_type' => LoyaltyRewardType::LaunchFifty,
                    'reserved_reward_amount' => $discount,
                ])->save();

                return [
                    'service_fee_discount' => $discount,
                    'loyalty_reward_type' => LoyaltyRewardType::LaunchFifty,
                ];
            });
        }

        if ($account->hasPendingReward()) {
            $discount = number_format((float) $account->pending_reward_amount, 2, '.', '');

            if (bccomp($discount, $serviceFee, 2) === 1) {
                $discount = $serviceFee;
            }

            $account->forceFill([
                'reserved_order_id' => $order->id,
                'reserved_reward_type' => LoyaltyRewardType::StreakReward,
                'reserved_reward_amount' => $discount,
                'pending_reward_amount' => null,
                'pending_reward_calculator' => null,
            ])->save();

            return [
                'service_fee_discount' => $discount,
                'loyalty_reward_type' => LoyaltyRewardType::StreakReward,
            ];
        }

        return [
            'service_fee_discount' => '0.00',
            'loyalty_reward_type' => null,
        ];
    }

    public function handleOrderDelivered(Order $order): void
    {
        DB::transaction(function () use ($order): void {
            /** @var Order $locked */
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            $account = CustomerLoyaltyAccount::query()
                ->where('customer_id', $locked->customer_id)
                ->lockForUpdate()
                ->first();

            if ($account === null) {
                $account = CustomerLoyaltyAccount::query()->create([
                    'customer_id' => $locked->customer_id,
                    'qualifying_orders_count' => 0,
                ]);
                $account = CustomerLoyaltyAccount::query()
                    ->whereKey($account->id)
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            $rewardType = $locked->loyalty_reward_type;

            if ($rewardType === LoyaltyRewardType::LaunchFifty) {
                $account->forceFill([
                    'launch_consumed_at' => now(),
                    'launch_order_id' => $locked->id,
                    'reserved_order_id' => null,
                    'reserved_reward_type' => null,
                    'reserved_reward_amount' => null,
                ])->save();

                return;
            }

            if ($rewardType === LoyaltyRewardType::StreakReward) {
                $account->forceFill([
                    'qualifying_orders_count' => 0,
                    'pending_reward_amount' => null,
                    'pending_reward_calculator' => null,
                    'reserved_order_id' => null,
                    'reserved_reward_type' => null,
                    'reserved_reward_amount' => null,
                ])->save();

                return;
            }

            $required = $this->requiredOrders();
            $next = min($required, $account->qualifying_orders_count + 1);

            $updates = [
                'qualifying_orders_count' => $next,
            ];

            if ($next >= $required && ! $account->hasPendingReward() && $account->reserved_order_id === null) {
                $customer = Customer::query()->findOrFail($locked->customer_id);
                $updates['pending_reward_amount'] = $this->discountCalculator->amountFor($customer, $locked);
                $updates['pending_reward_calculator'] = class_basename($this->discountCalculator::class);
                $updates['qualifying_orders_count'] = $required;
            }

            $account->forceFill($updates)->save();
        });
    }

    public function handleOrderCancelled(Order $order, OrderStatus $previousStatus): void
    {
        DB::transaction(function () use ($order, $previousStatus): void {
            $account = CustomerLoyaltyAccount::query()
                ->where('customer_id', $order->customer_id)
                ->lockForUpdate()
                ->first();

            if ($account === null) {
                return;
            }

            if ((int) $account->reserved_order_id !== (int) $order->id) {
                return;
            }

            $rewardType = $account->reserved_reward_type;
            $early = $previousStatus->isEarlyCustomerCancelWindow();

            if ($early) {
                $restorePending = $rewardType === LoyaltyRewardType::StreakReward
                    ? $account->reserved_reward_amount
                    : null;

                $account->forceFill([
                    'reserved_order_id' => null,
                    'reserved_reward_type' => null,
                    'reserved_reward_amount' => null,
                    'pending_reward_amount' => $restorePending ?? $account->pending_reward_amount,
                    'pending_reward_calculator' => $restorePending !== null
                        ? ($account->pending_reward_calculator ?? class_basename($this->discountCalculator::class))
                        : $account->pending_reward_calculator,
                ])->save();

                return;
            }

            // Cancelled after accept: burn the reserved streak reward and reset cycle.
            if ($rewardType === LoyaltyRewardType::StreakReward) {
                $account->forceFill([
                    'qualifying_orders_count' => 0,
                    'pending_reward_amount' => null,
                    'pending_reward_calculator' => null,
                    'reserved_order_id' => null,
                    'reserved_reward_type' => null,
                    'reserved_reward_amount' => null,
                ])->save();

                return;
            }

            // Launch reservation released without consuming the global launch slot.
            $account->forceFill([
                'reserved_order_id' => null,
                'reserved_reward_type' => null,
                'reserved_reward_amount' => null,
            ])->save();
        });
    }

    public function netServiceFee(string $serviceFee, string $discount): string
    {
        if (bccomp($discount, '0', 2) !== 1) {
            return number_format((float) $serviceFee, 2, '.', '');
        }

        if (bccomp($discount, $serviceFee, 2) === 1) {
            return '0.00';
        }

        return bcsub($serviceFee, $discount, 2);
    }

    private function canApplyLaunchDiscount(Customer $customer, CustomerLoyaltyAccount $account): bool
    {
        if ($account->launch_consumed_at !== null || $account->launch_order_id !== null) {
            return false;
        }

        if ($account->reserved_reward_type === LoyaltyRewardType::LaunchFifty) {
            return false;
        }

        if ($this->customerHasCompletedOrder($customer->id)) {
            return false;
        }

        return $this->launchSlotsRemaining() > 0;
    }

    private function customerHasCompletedOrder(int $customerId): bool
    {
        return Order::query()
            ->where('customer_id', $customerId)
            ->where('order_status', OrderStatus::Delivered)
            ->exists();
    }

    private function launchSlotsRemaining(): int
    {
        $max = (int) config('business.loyalty.launch.max_customers', 25);

        $consumed = CustomerLoyaltyAccount::query()
            ->whereNotNull('launch_consumed_at')
            ->count();

        $reserved = CustomerLoyaltyAccount::query()
            ->where('reserved_reward_type', LoyaltyRewardType::LaunchFifty)
            ->whereNotNull('reserved_order_id')
            ->count();

        return max(0, $max - $consumed - $reserved);
    }

    private function launchDiscountAmount(string $serviceFee): string
    {
        $percent = (float) config('business.loyalty.launch.service_fee_percent', 50);
        $discount = bcmul($serviceFee, number_format($percent / 100, 4, '.', ''), 2);

        if (bccomp($discount, $serviceFee, 2) === 1) {
            return $serviceFee;
        }

        return $discount;
    }

    private function baseServiceFee(): string
    {
        return number_format((float) config('business.orders.service_fee', 50), 2, '.', '');
    }

    private function requiredOrders(): int
    {
        return max(1, (int) config('business.loyalty.streak.required_orders', 5));
    }
}
