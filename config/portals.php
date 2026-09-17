<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Portal hosts
    |--------------------------------------------------------------------------
    |
    | When enabled, each portal is bound to its own host so session cookies
    | stay separate (keep SESSION_DOMAIN=null). Leave disabled for single-host
    | local development (e.g. php artisan serve on 127.0.0.1:8000).
    |
    */

    'enabled' => (bool) env('PORTALS_BY_HOST', false),

    'scheme' => env('PORTAL_SCHEME', parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_SCHEME) ?: 'https'),

    'hosts' => [
        'storefront' => env('PORTAL_STOREFRONT_HOST', 'chisdrive.com'),
        'admin' => env('PORTAL_ADMIN_HOST', 'admin.chisdrive.com'),
        'business' => env('PORTAL_BUSINESS_HOST', 'business.chisdrive.com'),
        'driver' => env('PORTAL_DRIVER_HOST', 'driver.chisdrive.com'),
    ],

];
