<?php

return [
    'inactive_days' => (int) env('REACTIVATION_INACTIVE_DAYS', 30),
    'frequency_cooldown_days' => (int) env('REACTIVATION_FREQUENCY_COOLDOWN_DAYS', 14),
    'audience_limit' => (int) env('REACTIVATION_AUDIENCE_LIMIT', 500),
    'suggestion_horizon_days' => (int) env('REACTIVATION_SUGGESTION_HORIZON_DAYS', 28),
];
