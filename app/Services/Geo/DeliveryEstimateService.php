<?php

namespace App\Services\Geo;

use App\Models\BusinessBranch;
use App\Models\Order;
use App\Support\GeoPoint;

final class DeliveryEstimateService
{
    public function __construct(
        private readonly DistanceService $distance,
    ) {}

    /**
     * @return array{minutes_min: int, minutes_max: int, travel_seconds: int, method: string}|null
     */
    public function estimateForPoints(
        GeoPoint $pickup,
        GeoPoint $delivery,
        ?int $preparationMinutes = null,
    ): ?array {
        $measure = $this->distance->measure($pickup, $delivery);
        $prep = max(0, $preparationMinutes ?? (int) config('business.orders.default_preparation_minutes', 20));
        $travelMinutes = (int) ceil($measure['duration_seconds'] / 60);
        $total = $prep + $travelMinutes;

        return [
            'minutes_min' => max(5, $total - 5),
            'minutes_max' => $total + 5,
            'travel_seconds' => $measure['duration_seconds'],
            'method' => $measure['method']->value,
        ];
    }

    /**
     * @return array{minutes_min: int, minutes_max: int, travel_seconds: int, method: string}|null
     */
    public function estimateForBranchDelivery(
        BusinessBranch $branch,
        float $deliveryLatitude,
        float $deliveryLongitude,
        ?int $preparationMinutes = null,
    ): ?array {
        if ($branch->latitude === null || $branch->longitude === null) {
            return null;
        }

        return $this->estimateForPoints(
            GeoPoint::make($branch->latitude, $branch->longitude),
            GeoPoint::make($deliveryLatitude, $deliveryLongitude),
            $preparationMinutes,
        );
    }

    /**
     * @return array{minutes_min: int, minutes_max: int}|null
     */
    public function estimateForOrder(Order $order): ?array
    {
        $order->loadMissing(['pickupAddress', 'deliveryAddress', 'logistics']);

        $prep = $order->estimated_preparation_minutes;
        $travelSeconds = $order->logistics?->estimated_delivery_duration_seconds;

        if ($travelSeconds === null) {
            $pickup = $order->pickupAddress;
            $delivery = $order->deliveryAddress;

            if ($pickup?->latitude === null || $pickup->longitude === null
                || $delivery?->latitude === null || $delivery->longitude === null) {
                return null;
            }

            return $this->estimateForPoints(
                GeoPoint::make($pickup->latitude, $pickup->longitude),
                GeoPoint::make($delivery->latitude, $delivery->longitude),
                $prep,
            );
        }

        $prepMinutes = max(0, $prep ?? 20);
        $travelMinutes = (int) ceil($travelSeconds / 60);
        $total = $prepMinutes + $travelMinutes;

        return [
            'minutes_min' => max(5, $total - 5),
            'minutes_max' => $total + 5,
        ];
    }
}
