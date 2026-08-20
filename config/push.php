<?php

return [

    'enabled' => (bool) env('PUSH_ENABLED', false),

    'driver' => env('PUSH_DRIVER', 'fcm'), // fcm|null|log

    'fcm' => [
        'project_id' => env('FIREBASE_PROJECT_ID', ''),
        // Absolute path to service account JSON, or leave empty and use credentials_json.
        'credentials' => env('FIREBASE_CREDENTIALS', ''),
        // Raw JSON string for environments without a mounted file. Never commit real secrets.
        'credentials_json' => env('FIREBASE_CREDENTIALS_JSON', ''),
    ],

    'web' => [
        'api_key' => env('VITE_FIREBASE_API_KEY', env('FIREBASE_WEB_API_KEY', '')),
        'auth_domain' => env('VITE_FIREBASE_AUTH_DOMAIN', env('FIREBASE_AUTH_DOMAIN', '')),
        'project_id' => env('VITE_FIREBASE_PROJECT_ID', env('FIREBASE_PROJECT_ID', '')),
        'storage_bucket' => env('VITE_FIREBASE_STORAGE_BUCKET', env('FIREBASE_STORAGE_BUCKET', '')),
        'messaging_sender_id' => env('VITE_FIREBASE_MESSAGING_SENDER_ID', env('FIREBASE_MESSAGING_SENDER_ID', '')),
        'app_id' => env('VITE_FIREBASE_APP_ID', env('FIREBASE_APP_ID', '')),
        'vapid_key' => env('VITE_FIREBASE_VAPID_KEY', env('FIREBASE_VAPID_KEY', '')),
    ],

    'ttl' => [
        'driver_offer_seconds' => (int) env('PUSH_DRIVER_OFFER_TTL', 120),
        'order_update_seconds' => (int) env('PUSH_ORDER_UPDATE_TTL', 3600),
        'default_seconds' => (int) env('PUSH_DEFAULT_TTL', 86400),
    ],

    'rating_prompt_delay_minutes' => (int) env('PUSH_RATING_PROMPT_DELAY_MINUTES', 1440),

    'dedupe_ttl_seconds' => (int) env('PUSH_DEDUPE_TTL', 120),

];
