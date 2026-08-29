<?php

return [
    // This short delay gives refunds/reviews time to be resolved before money is
    // included in a manual owner payout. Availability is derived at query time,
    // so no scheduler is required.
    'availability_delay_days' => max(0, (int) env('OWNER_PAYOUT_DELAY_DAYS', 2)),

    // Owners may request a payout after their ready balance reaches this
    // amount. Store this as integer centavos so the rule never depends on
    // browser input or floating-point currency arithmetic.
    'minimum_request_amount_centavos' => max(
        0,
        (int) env('OWNER_PAYOUT_MINIMUM_CENTAVOS', 50000),
    ),
];
