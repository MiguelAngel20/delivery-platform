<?php

return [

    'customer' => [
        'base_score' => 50,
        'min_score' => 0,
        'max_score' => 100,
        'points' => [
            'completed_order' => 4,
            'early_cancellation' => -1,
            'late_cancellation' => -8,
            'rejected_at_delivery' => -15,
            'payment_incident' => -12,
            'responsible_incident' => -8,
        ],
        'levels' => [
            'new_max_completed_orders' => 2,
            'trusted_min_completed_orders' => 10,
            'trusted_max_late_cancellations' => 0,
            'trusted_max_rejected_at_delivery' => 0,
            'trusted_max_payment_incidents' => 0,
            'trusted_min_score' => 70,
            'restricted_late_cancellations' => 3,
            'restricted_rejected_at_delivery' => 2,
            'restricted_payment_incidents' => 2,
            'restricted_responsible_incidents' => 3,
            'restricted_max_score' => 40,
        ],
        'late_statuses' => [
            'preparing',
            'searching_driver',
            'ready_for_pickup',
            'driver_assigned',
            'driver_at_business',
            'picked_up',
            'on_the_way',
        ],
        'delivery_refusal_statuses' => [
            'picked_up',
            'on_the_way',
        ],
    ],

    'driver' => [
        'base_score' => 50,
        'min_score' => 0,
        'max_score' => 100,
        'points' => [
            'completed_order' => 2,
            'responsible_cancellation' => -10,
            'responsible_incident' => -12,
            'rating_delta_weight' => 8,
        ],
        'requires_review_max_score' => 40,
        'quality_levels' => [
            ['min' => 80, 'key' => 'high', 'label' => 'Alto'],
            ['min' => 55, 'key' => 'good', 'label' => 'Bueno'],
            ['min' => 0, 'key' => 'review', 'label' => 'En observación'],
        ],
    ],

];
