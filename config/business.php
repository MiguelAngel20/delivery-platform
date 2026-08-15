<?php

return [

    'defaults' => [
        'max_branches' => 1,
        'max_business_admins' => 1,
        'max_employees_per_branch' => 3,
    ],

    'orders' => [
        'service_fee' => (float) env('ORDER_SERVICE_FEE', 50),
        'delivery_fee' => (float) env('ORDER_DELIVERY_FEE', 0),
        'max_customer_addresses' => (int) env('MAX_CUSTOMER_ADDRESSES', 4),
        'default_preparation_minutes' => (int) env('ORDER_DEFAULT_PREPARATION_MINUTES', 20),
    ],

    'custom_orders' => [
        'max_active_requests' => (int) env('MAX_ACTIVE_CUSTOM_REQUESTS', 2),
    ],

    'dispatch' => [
        'max_active_orders_per_driver' => (int) env('MAX_ACTIVE_ORDERS_PER_DRIVER', 3),
    ],

    'finance' => [
        'cash' => [
            'collection_party' => env('FINANCE_CASH_COLLECTION_PARTY', 'driver'),
            'driver_pays_business_on_pickup' => (bool) env('FINANCE_DRIVER_PAYS_BUSINESS_ON_PICKUP', true),
        ],
        'allocation' => [
            'driver_service_fee_share' => (float) env('FINANCE_DRIVER_SERVICE_FEE_SHARE', 1),
            'platform_service_fee_share' => (float) env('FINANCE_PLATFORM_SERVICE_FEE_SHARE', 0),
            'driver_receives_delivery_fee' => (bool) env('FINANCE_DRIVER_RECEIVES_DELIVERY_FEE', true),
        ],
    ],

];
