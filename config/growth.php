<?php

return [
    'lookback_days' => 42,
    'recommendation_ttl_hours' => 24,
    'owner_limit' => 5,
    'admin_limit' => 20,

    'empty_inventory' => [
        'horizon_days' => 7,
        'scan_limit' => 250,
        'minimum_slots' => 6,
    ],

    'demand' => [
        'minimum_searches' => 6,
        'minimum_unfulfilled_searches' => 3,
        'minimum_available_slots' => 3,
    ],

    'inactive_customers' => [
        'minimum_customers' => 3,
    ],

    'successful_campaign' => [
        'minimum_bookings' => 3,
    ],

    'low_conversion' => [
        'minimum_profile_views' => 20,
        'minimum_unique_visitors' => 10,
        'maximum_booking_rate_percent' => 5.0,
    ],

    'channel_comparison' => [
        'minimum_visitors_per_channel' => 20,
        'minimum_total_bookings' => 3,
        'minimum_gap_percentage_points' => 5.0,
    ],

    'snooze_days' => [7, 30],
];
