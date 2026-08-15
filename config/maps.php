<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Google Maps API keys
    |--------------------------------------------------------------------------
    |
    | Browser key (frontend): restrict by HTTP referrers in Google Cloud Console
    | for each environment (local, staging, production). Enable Maps JavaScript
    | API and Places API.
    |
    | Server key (optional): restrict by IP. Enable Distance Matrix API and/or
    | Routes API. Never expose this key to Vite or the browser.
    |
    */

    'browser_api_key' => env('GOOGLE_MAPS_API_KEY', ''),

    'server_api_key' => env('GOOGLE_MAPS_SERVER_API_KEY', env('GOOGLE_MAPS_API_KEY', '')),

    'default_center' => [
        'latitude' => (float) env('MAPS_DEFAULT_LATITUDE', 16.2514000),
        'longitude' => (float) env('MAPS_DEFAULT_LONGITUDE', -92.1342000),
        'zoom' => (int) env('MAPS_DEFAULT_ZOOM', 14),
    ],

    /*
    | Preferred distance mode for checkout snapshots.
    | straight_line = Haversine only (no Google call)
    | route_distance = Google road distance with Haversine fallback
    */
    'distance_mode' => env('MAPS_DISTANCE_MODE', 'route_distance'),

    'route_cache_ttl_seconds' => (int) env('MAPS_ROUTE_CACHE_TTL', 3600),

    'driver_location_freshness_minutes' => (int) env('DRIVER_LOCATION_FRESHNESS_MINUTES', 15),

    'geocode_rate_limit_per_minute' => (int) env('MAPS_GEOCODE_RATE_LIMIT', 30),

    'distance_rate_limit_per_minute' => (int) env('MAPS_DISTANCE_RATE_LIMIT', 30),

];
