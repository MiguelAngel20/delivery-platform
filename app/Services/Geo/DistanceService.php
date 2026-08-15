<?php

namespace App\Services\Geo;

use App\Contracts\MapsClient;
use App\Enums\DistanceMethod;
use App\Support\GeoPoint;

/**
 * @phpstan-type RouteResult array{
 *     distance_meters: int,
 *     duration_seconds: int,
 *     method: DistanceMethod
 * }
 */
final class DistanceService
{
    public function __construct(
        private readonly MapsClient $maps,
    ) {}

    /**
     * Haversine great-circle distance in kilometers.
     */
    public function haversineKm(GeoPoint $from, GeoPoint $to): float
    {
        $earthRadiusKm = 6371.0;

        $latFrom = deg2rad($from->latitude);
        $latTo = deg2rad($to->latitude);
        $deltaLat = deg2rad($to->latitude - $from->latitude);
        $deltaLng = deg2rad($to->longitude - $from->longitude);

        $a = sin($deltaLat / 2) ** 2
            + cos($latFrom) * cos($latTo) * sin($deltaLng / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadiusKm * $c;
    }

    public function haversineMeters(GeoPoint $from, GeoPoint $to): int
    {
        return (int) round($this->haversineKm($from, $to) * 1000);
    }

    /**
     * @return RouteResult
     */
    public function measure(GeoPoint $from, GeoPoint $to, ?DistanceMethod $preferred = null): array
    {
        $preferred ??= DistanceMethod::tryFrom((string) config('maps.distance_mode', 'route_distance'))
            ?? DistanceMethod::RouteDistance;

        if ($preferred === DistanceMethod::StraightLine) {
            return $this->straightLineResult($from, $to);
        }

        $route = $this->maps->routeDistance($from, $to);

        if ($route !== null) {
            return [
                'distance_meters' => $route['distance_meters'],
                'duration_seconds' => $route['duration_seconds'],
                'method' => DistanceMethod::RouteDistance,
            ];
        }

        return $this->straightLineResult($from, $to);
    }

    /**
     * @return RouteResult
     */
    private function straightLineResult(GeoPoint $from, GeoPoint $to): array
    {
        $meters = $this->haversineMeters($from, $to);

        // Rough travel estimate: ~25 km/h urban average for fallback only.
        $duration = $meters === 0 ? 0 : (int) max(60, round(($meters / 1000) / 25 * 3600));

        return [
            'distance_meters' => $meters,
            'duration_seconds' => $duration,
            'method' => DistanceMethod::StraightLine,
        ];
    }
}
