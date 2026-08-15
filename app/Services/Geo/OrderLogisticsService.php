<?php

namespace App\Services\Geo;

use App\Enums\DistanceMethod;
use App\Models\BusinessBranch;
use App\Models\CoverageZone;
use App\Models\Order;
use App\Models\OrderLogistics;
use App\Support\GeoPoint;
use Illuminate\Validation\ValidationException;

final class OrderLogisticsService
{
    public function __construct(
        private readonly CoverageService $coverage,
        private readonly DistanceService $distance,
        private readonly DeliveryPricingService $pricing,
    ) {}

    /**
     * @return array{distance_meters: int, duration_seconds: int, method: DistanceMethod, zone: CoverageZone|null, delivery_fee: string}
     */
    public function assertAndMeasure(
        ?BusinessBranch $branch,
        float $deliveryLatitude,
        float $deliveryLongitude,
        ?GeoPoint $pickupOverride = null,
    ): array {
        if (! $this->coverage->isOrderCovered($branch, $deliveryLatitude, $deliveryLongitude)) {
            throw ValidationException::withMessages([
                'delivery' => 'Por el momento no realizamos entregas en esta ubicación.',
            ]);
        }

        $pickup = $pickupOverride;

        if ($pickup === null) {
            if ($branch === null || $branch->latitude === null || $branch->longitude === null) {
                throw ValidationException::withMessages([
                    'branch_id' => 'La sucursal no tiene coordenadas de recogida.',
                ]);
            }

            $pickup = GeoPoint::make($branch->latitude, $branch->longitude);
        }

        $delivery = GeoPoint::make($deliveryLatitude, $deliveryLongitude);
        $measure = $this->distance->measure($pickup, $delivery);
        $zone = $this->coverage->getApplicableZone($branch, $deliveryLatitude, $deliveryLongitude);

        return [
            ...$measure,
            'zone' => $zone,
            'delivery_fee' => $this->pricing->quote($branch, [
                'distance_meters' => $measure['distance_meters'],
                'coverage_zone_id' => $zone?->id,
            ]),
        ];
    }

    /**
     * @param  array{distance_meters: int, duration_seconds: int, method: DistanceMethod, zone: CoverageZone|null}  $snapshot
     */
    public function storeSnapshot(Order $order, array $snapshot): OrderLogistics
    {
        $zone = $snapshot['zone'] ?? null;

        return OrderLogistics::query()->updateOrCreate(
            ['order_id' => $order->id],
            [
                'pickup_to_delivery_distance_meters' => $snapshot['distance_meters'],
                'estimated_delivery_duration_seconds' => $snapshot['duration_seconds'],
                'distance_method' => $snapshot['method'],
                'coverage_zone_id' => $zone?->id,
                'coverage_zone_name' => $zone?->name,
                'coverage_zone_type' => $zone?->zone_type?->value,
                'coverage_radius_meters' => $zone?->radius_meters,
            ],
        );
    }
}
