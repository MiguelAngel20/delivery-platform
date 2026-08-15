<?php

namespace App\Services\Geo;

use App\Contracts\MapsClient;
use App\Support\GeoPoint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class GoogleMapsClient implements MapsClient
{
    /**
     * @return array{distance_meters: int, duration_seconds: int}|null
     */
    public function routeDistance(GeoPoint $from, GeoPoint $to): ?array
    {
        $apiKey = (string) config('maps.server_api_key');

        if ($apiKey === '') {
            return null;
        }

        $cacheKey = sprintf(
            'maps:route:%s:%s:%s:%s',
            number_format($from->latitude, 5, '.', ''),
            number_format($from->longitude, 5, '.', ''),
            number_format($to->latitude, 5, '.', ''),
            number_format($to->longitude, 5, '.', ''),
        );

        $ttl = (int) config('maps.route_cache_ttl_seconds', 3600);

        try {
            return Cache::remember($cacheKey, $ttl, function () use ($from, $to, $apiKey): ?array {
                $response = Http::timeout(8)
                    ->get('https://maps.googleapis.com/maps/api/distancematrix/json', [
                        'origins' => "{$from->latitude},{$from->longitude}",
                        'destinations' => "{$to->latitude},{$to->longitude}",
                        'mode' => 'driving',
                        'units' => 'metric',
                        'key' => $apiKey,
                    ]);

                if (! $response->successful()) {
                    return null;
                }

                $element = data_get($response->json(), 'rows.0.elements.0');

                if (! is_array($element) || ($element['status'] ?? null) !== 'OK') {
                    return null;
                }

                $distance = (int) data_get($element, 'distance.value', 0);
                $duration = (int) data_get($element, 'duration.value', 0);

                if ($distance < 0 || $duration < 0) {
                    return null;
                }

                return [
                    'distance_meters' => $distance,
                    'duration_seconds' => $duration,
                ];
            });
        } catch (Throwable $exception) {
            Log::warning('Google Maps route distance failed', [
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }
}
