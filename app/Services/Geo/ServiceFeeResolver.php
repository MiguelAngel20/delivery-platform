<?php

namespace App\Services\Geo;

use App\Models\BusinessBranch;
use App\Models\ServiceFeeDistanceSetting;
use App\Support\GeoPoint;

final class ServiceFeeResolver
{
    public function __construct(
        private readonly DistanceService $distance,
        private readonly ServiceFeeDistanceCalculator $calculator,
    ) {}

    /**
     * @return array{
     *     service_fee: string,
     *     distance_meters: int|null,
     *     settings: array{base_meters: int, base_fee: string, step_meters: int, step_fee: string}
     * }
     */
    public function quote(
        ?float $latitude,
        ?float $longitude,
        ?BusinessBranch $branch = null,
        ?int $distanceMeters = null,
    ): array {
        $settings = ServiceFeeDistanceSetting::current();
        $settingsPayload = [
            'base_meters' => (int) $settings->base_meters,
            'base_fee' => number_format((float) $settings->base_fee, 2, '.', ''),
            'step_meters' => (int) $settings->step_meters,
            'step_fee' => number_format((float) $settings->step_fee, 2, '.', ''),
        ];

        if ($distanceMeters !== null) {
            return [
                'service_fee' => $this->calculator->calculate($distanceMeters, $settings),
                'distance_meters' => max(0, $distanceMeters),
                'settings' => $settingsPayload,
            ];
        }

        if (
            $latitude === null
            || $longitude === null
            || $branch === null
            || $branch->latitude === null
            || $branch->longitude === null
        ) {
            return [
                'service_fee' => number_format((float) $settings->base_fee, 2, '.', ''),
                'distance_meters' => null,
                'settings' => $settingsPayload,
            ];
        }

        $measure = $this->distance->measure(
            GeoPoint::make($branch->latitude, $branch->longitude),
            GeoPoint::make($latitude, $longitude),
        );

        return [
            'service_fee' => $this->calculator->calculate($measure['distance_meters'], $settings),
            'distance_meters' => $measure['distance_meters'],
            'settings' => $settingsPayload,
        ];
    }

    public function resolve(
        ?float $latitude,
        ?float $longitude,
        ?BusinessBranch $branch = null,
        ?int $distanceMeters = null,
    ): string {
        return $this->quote($latitude, $longitude, $branch, $distanceMeters)['service_fee'];
    }

    public function defaultFee(): string
    {
        $settings = ServiceFeeDistanceSetting::current();

        return number_format((float) $settings->base_fee, 2, '.', '');
    }
}
