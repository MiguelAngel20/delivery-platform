<?php

namespace App\Services\Dispatch;

use App\Enums\BusinessDeliveryMode;
use App\Enums\BusinessOperationMode;
use App\Enums\DriverAssignmentStatus;
use App\Enums\DriverScope;
use App\Enums\OrderType;
use App\Models\Driver;
use App\Models\Order;
use App\Support\OrderActiveStatuses;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class AvailableOrdersQuery
{
    public function __construct(
        private readonly DriverEligibilityService $eligibility,
        private readonly DriverRankingService $ranking,
    ) {}

    /**
     * @return Collection<int, Order>
     */
    public function forDriver(Driver $driver): Collection
    {
        $driver->loadMissing(['user', 'businesses']);

        $businessIds = $driver->businesses->pluck('id');

        $orders = Order::query()
            ->with([
                'branch.business',
                'deliveryAddress',
                'pickupAddress',
                'customer.user',
            ])
            ->whereNull('assigned_driver_id')
            ->whereIn('order_status', OrderActiveStatuses::offerableValues())
            ->whereDoesntHave('assignments', function (Builder $query) use ($driver): void {
                $query->where('driver_id', $driver->id)
                    ->whereIn('status', [
                        DriverAssignmentStatus::Rejected->value,
                        DriverAssignmentStatus::Expired->value,
                    ]);
            })
            ->where(function (Builder $query) use ($driver, $businessIds): void {
                if ($driver->driver_scope === DriverScope::Platform) {
                    $query->where(function (Builder $platform): void {
                        $platform->where('type', OrderType::Custom->value)
                            ->orWhere('operation_mode', BusinessOperationMode::PlatformOperated->value);
                    });
                }

                $query->orWhere(function (Builder $partner) use ($driver, $businessIds): void {
                    $partner->where('type', OrderType::Business->value)
                        ->where('operation_mode', BusinessOperationMode::Partner->value)
                        ->whereHas('branch.business', function (Builder $businessQuery) use ($driver, $businessIds): void {
                            $businessQuery->where('delivery_mode', '!=', BusinessDeliveryMode::None->value);

                            if ($driver->driver_scope === DriverScope::BusinessOnly) {
                                $businessQuery->whereIn('delivery_mode', [
                                    BusinessDeliveryMode::OwnDrivers->value,
                                    BusinessDeliveryMode::Hybrid->value,
                                ])->whereIn('id', $businessIds->all());
                            } else {
                                $businessQuery->whereIn('delivery_mode', [
                                    BusinessDeliveryMode::PlatformDrivers->value,
                                    BusinessDeliveryMode::Hybrid->value,
                                ]);
                            }
                        });
                });
            })
            ->latest('id')
            ->limit(50)
            ->get();

        $eligible = $orders
            ->filter(fn (Order $order): bool => $this->eligibility->isDriverEligibleForOrder($driver, $order))
            ->values();

        return $this->ranking->sortOrdersByProximity($driver, $eligible);
    }
}
