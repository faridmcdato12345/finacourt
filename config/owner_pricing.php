<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Founding venue pilot
    |--------------------------------------------------------------------------
    |
    | These values are the public commercial terms displayed to prospective
    | court owners. Money is stored as integer centavos and percentage fees as
    | basis points so the page never relies on floating-point pricing.
    |
    | This is product pricing only. It does not enable subscription billing or
    | alter booking/payment calculations elsewhere in the application.
    |
    */
    'pilot' => [
        'name' => env('OWNER_PILOT_PLAN_NAME', 'Founding venue pilot'),
        'monthly_fee_centavos' => (int) env('OWNER_PILOT_MONTHLY_FEE_CENTAVOS', 0),
        'booking_fee_basis_points' => (int) env('OWNER_PILOT_BOOKING_FEE_BASIS_POINTS', 0),
        'availability' => env('OWNER_PILOT_AVAILABILITY', 'Accepting pilot venues'),
        'features' => [
            'Public venue and court listings',
            'Server-checked availability and reservations',
            'Owner booking and inventory workspace',
            'Demand insights and clear growth opportunities',
            'Open-slot deals with booking-source reporting',
            'Previous-customer comeback tools with consent controls',
            'Visibility checklist, QR booking links, and map directions',
            'Traffic, booking-intent, customer, and booking-value reports',
            'Manual and pay-at-venue payment tracking',
        ],
    ],

    'sales_email' => env('OWNER_SALES_EMAIL', env('MAIL_FROM_ADDRESS', 'hello@example.com')),
];
