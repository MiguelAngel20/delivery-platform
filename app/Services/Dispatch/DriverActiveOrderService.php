<?php

namespace App\Services\Dispatch;

use App\Enums\DeliveryTripStatus;
use App\Enums\DriverAvailabilityStatus;
use App\Enums\OrderStatus;
use App\Models\DeliveryTrip;
use App\Models\Driver;
use App\Models\Order;
use App\Support\OrderActiveStatuses;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class DriverActiveOrderService
{
    /**
     * @return Collection<int, Order>
     */
    public function activeOrdersFor(Driver $driver): Collection
    {
        return Order::query()
            ->where('assigned_driver_id', $driver->id)
            ->whereIn('order_status', OrderActiveStatuses::forDriverValues())
            ->with(['branch.business', 'deliveryAddress', 'customer.user'])
            ->orderBy('created_at')
            ->get();
    }

    public function activeCount(Driver $driver): int
    {
        return Order::query()
            ->where('assigned_driver_id', $driver->id)
            ->whereIn('order_status', OrderActiveStatuses::forDriverValues())
            ->count();
    }

    public function openTripForBranch(Driver $driver, int $branchId): ?DeliveryTrip
    {
        return DeliveryTrip::query()
            ->where('driver_id', $driver->id)
            ->where('branch_id', $branchId)
            ->whereIn('status', [
                DeliveryTripStatus::Open->value,
                DeliveryTripStatus::InProgress->value,
            ])
            ->latest('id')
            ->first();
    }

    public function markBusy(Driver $driver): void
    {
        DB::transaction(function () use ($driver): void {
            /** @var Driver $locked */
            $locked = Driver::query()
                ->whereKey($driver->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->availability_status !== DriverAvailabilityStatus::Available) {
                return;
            }

            Driver::query()
                ->whereKey($locked->id)
                ->where('availability_status', DriverAvailabilityStatus::Available)
                ->update([
                    'availability_status' => DriverAvailabilityStatus::Busy,
                ]);
        });
    }

    public function maybeMarkAvailable(Driver $driver): void
    {
        DB::transaction(function () use ($driver): void {
            /** @var Driver $locked */
            $locked = Driver::query()
                ->whereKey($driver->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($this->activeCount($locked) > 0) {
                return;
            }

            if ($locked->availability_status !== DriverAvailabilityStatus::Busy) {
                return;
            }

            Driver::query()
                ->whereKey($locked->id)
                ->where('availability_status', DriverAvailabilityStatus::Busy)
                ->update([
                    'availability_status' => DriverAvailabilityStatus::Available,
                ]);
        });
    }

    public function completeTripIfFinished(DeliveryTrip $trip): void
    {
        $trip->loadMissing('orders');

        $allFinished = $trip->orders->every(function (Order $order): bool {
            return in_array($order->order_status, [
                OrderStatus::Delivered,
                OrderStatus::Cancelled,
                OrderStatus::Rejected,
            ], true);
        });

        if (! $allFinished || $trip->orders->isEmpty()) {
            return;
        }

        $trip->forceFill([
            'status' => DeliveryTripStatus::Completed,
            'completed_at' => now(),
        ])->save();
    }
}
