<?php

return [
    'enabled' => filter_var(env('OWNER_PAYOUT_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
    'currency' => strtoupper((string) env('OWNER_PAYOUT_CURRENCY', 'PHP')),
    'timezone' => env('OWNER_PAYOUT_TIMEZONE', 'Asia/Manila'),

    // Starts only after the booked court time has ended. This is deliberately
    // configurable because cancellation, dispute, and provider-settlement
    // policies may change independently of application releases.
    'clearing_hours' => max(0, (int) env('OWNER_PAYOUT_CLEARING_HOURS', 24)),

    'provider' => env('OWNER_PAYOUT_PROVIDER', 'manual'),
    'transfer_fee_centavos' => max(0, (int) env('OWNER_PAYOUT_TRANSFER_FEE_CENTAVOS', 0)),

    'scheduled' => [
        'enabled' => filter_var(env('OWNER_SCHEDULED_PAYOUT_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
        'minimum_centavos' => max(1, (int) env('OWNER_SCHEDULED_PAYOUT_MINIMUM_CENTAVOS', 100)),
        // The application policy is the 15th and the true calendar month-end.
        'days' => ['15', 'LAST_DAY'],
    ],

    'early' => [
        'enabled' => filter_var(env('OWNER_EARLY_PAYOUT_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
        'minimum_centavos' => max(1, (int) env('OWNER_EARLY_PAYOUT_MINIMUM_CENTAVOS', 100)),
        'fee_payer' => in_array(env('OWNER_EARLY_PAYOUT_FEE_MODE', 'owner'), ['owner', 'platform'], true)
            ? env('OWNER_EARLY_PAYOUT_FEE_MODE', 'owner')
            : 'owner',
    ],
];
