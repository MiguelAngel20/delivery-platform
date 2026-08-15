<?php

namespace App\Contracts;

use App\Support\GeoPoint;

interface MapsClient
{
    /**
     * @return array{distance_meters: int, duration_seconds: int}|null
     */
    public function routeDistance(GeoPoint $from, GeoPoint $to): ?array;
}
