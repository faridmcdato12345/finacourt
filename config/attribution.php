<?php

return [
    // Attribution is informational. Browser markers are never trusted as
    // commission or payment evidence. The encrypted first-party continuity
    // cookie lets an identifiable visit survive an ordinary session timeout.
    'lookback_days' => (int) env('ATTRIBUTION_LOOKBACK_DAYS', 30),
    'cookie_name' => env('ATTRIBUTION_COOKIE_NAME', 'finacourt_acquisition'),
    'rule_version' => 'last_touch_with_promotion_override_v2',
];
