<?php

namespace App\Services\Platform;

use App\Enums\OrderStatus;
use App\Enums\PlatformSuspensionReason;
use App\Models\Customer;
use App\Models\Order;
use App\Models\PlatformActivitySuspension;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

final class PlatformActivitySuspensionService
{
    /**
     * Legacy key used when the Eloquent model was cached (caused __PHP_Incomplete_Class).
     */
    private const CACHE_KEY = 'platform.activity_suspension.current';

    public function current(): PlatformActivitySuspension
    {
        // Do not cache the Eloquent model: serialized models/enums break across requests.
        return PlatformActivitySuspension::current();
    }

    public function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public function isActive(): bool
    {
        return $this->current()->is_active;
    }

    public function assertOrderingAllowed(): void
    {
        $suspension = $this->current();

        if (! $suspension->is_active) {
            return;
        }

        /** @var PlatformSuspensionReason $reason */
        $reason = $suspension->reason;

        throw ValidationException::withMessages([
            'platform' => $reason->orderingBlockedMessage(),
        ]);
    }

    /**
     * @return array{
     *     active: bool,
     *     reason: string,
     *     reason_label: string,
     *     title: string,
     *     body: string,
     *     footnote: string,
     *     image_url: string,
     *     active_order_message: string|null,
     *     active_orders: list<array{id: int, order_number: string, status: string, status_label: string}>
     * }|null
     */
    public function storefrontPayload(?Customer $customer = null): ?array
    {
        $suspension = $this->current();

        if (! $suspension->is_active) {
            return null;
        }

        /** @var PlatformSuspensionReason $reason */
        $reason = $suspension->reason;
        $activeOrders = $this->activeOrdersFor($customer);

        return [
            'active' => true,
            'reason' => $reason->value,
            'reason_label' => $reason->label(),
            'title' => $reason->title(),
            'body' => $reason->body(),
            'footnote' => $reason->footnote(),
            'image_url' => $reason->imagePath(),
            'active_order_message' => $activeOrders === []
                ? null
                : $reason->activeOrderMessage(),
            'active_orders' => $activeOrders,
        ];
    }

    /**
     * @return list<array{id: int, order_number: string, status: string, status_label: string}>
     */
    private function activeOrdersFor(?Customer $customer): array
    {
        if ($customer === null) {
            return [];
        }

        $activeStatuses = array_values(array_filter(
            OrderStatus::cases(),
            fn (OrderStatus $status): bool => $status->isActiveForCustomer(),
        ));

        return Order::query()
            ->where('customer_id', $customer->id)
            ->whereIn('order_status', $activeStatuses)
            ->latest('id')
            ->limit(5)
            ->get(['id', 'order_number', 'order_status'])
            ->map(function (Order $order): array {
                /** @var OrderStatus $status */
                $status = $order->order_status;

                return [
                    'id' => $order->id,
                    'order_number' => (string) $order->order_number,
                    'status' => $status->value,
                    'status_label' => $status->customerLabel(),
                ];
            })
            ->all();
    }
}
