<?php

namespace App\Services\Dispatch;

use App\Models\Driver;
use App\Models\Order;
use App\Services\Drivers\DriverLocationService;
use App\Services\Geo\DistanceService;
use App\Support\GeoPoint;
use Illuminate\Support\Collection;

final class DriverRankingService
{
    public function __construct(
        private readonly DriverEligibilityService $eligibility,
        private readonly DriverLocationService $locations,
        private readonly DistanceService $distance,
    ) {}

    /**
     * Rank eligible drivers by proximity to pickup. Does not assign.
     *
     * @param  Collection<int, Driver>  $drivers
     * @return Collection<int, array{driver: Driver, distance_meters: int|null}>
     */
    public function rankEligibleDrivers(Collection $drivers, Order $order): Collection
    {
        $pickup = $this->pickupPoint($order);

        return $drivers
            ->filter(fn (Driver $driver): bool => $this->eligibility->isDriverEligibleForOrder($driver, $order))
            ->map(function (Driver $driver) use ($pickup): array {
                $point = $this->locations->freshPoint($driver);
                $meters = null;

                if ($pickup !== null && $point !== null) {
                    $meters = $this->distance->haversineMeters($point, $pickup);
                }

                return [
                    'driver' => $driver,
                    'distance_meters' => $meters,
                ];
            })
            ->sortBy(function (array $row): array {
                return [
                    $row['distance_meters'] === null ? 1 : 0,
                    $row['distance_meters'] ?? PHP_INT_MAX,
                ];
            })
            ->values();
    }

    /**
     * Sort available orders for a driver by distance to pickup.
     *
     * @param  Collection<int, Order>  $orders
     * @return Collection<int, Order>
     */
    public function sortOrdersByProximity(Driver $driver, Collection $orders): Collection
    {
        $driverPoint = $this->locations->freshPoint($driver);

        if ($driverPoint === null) {
            return $orders->values();
        }

        return $orders
            ->sortBy(function (Order $order) use ($driverPoint): int {
                $pickup = $this->pickupPoint($order);

                if ($pickup === null) {
                    return PHP_INT_MAX;
                }

                return $this->distance->haversineMeters($driverPoint, $pickup);
            })
            ->values();
    }

    public function distanceToPickupMeters(Driver $driver, Order $order): ?int
    {
        $driverPoint = $this->locations->freshPoint($driver);
        $pickup = $this->pickupPoint($order);

        if ($driverPoint === null || $pickup === null) {
            return null;
        }

        return $this->distance->haversineMeters($driverPoint, $pickup);
    }

    private function pickupPoint(Order $order): ?GeoPoint
    {
        $order->loadMissing(['pickupAddress', 'branch']);

        $pickup = $order->pickupAddress;

        if ($pickup?->latitude !== null && $pickup->longitude !== null) {
            return GeoPoint::make($pickup->latitude, $pickup->longitude);
        }

        $branch = $order->branch;

        if ($branch?->latitude !== null && $branch->longitude !== null) {
            return GeoPoint::make($branch->latitude, $branch->longitude);
        }

        return null;
    }
}
