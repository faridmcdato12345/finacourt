<?php

return [
    'hold_minutes' => (int) env('BOOKING_HOLD_MINUTES', 15),
    'maximum_hold_minutes' => (int) env('BOOKING_MAXIMUM_HOLD_MINUTES', 60),
];
