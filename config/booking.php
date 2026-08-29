<?php

return [
    'hold_minutes' => (int) env('BOOKING_HOLD_MINUTES', 15),
    'maximum_hold_minutes' => (int) env('BOOKING_MAXIMUM_HOLD_MINUTES', 60),
    'maximum_player_booking_minutes' => max(
        15,
        (int) env('BOOKING_MAXIMUM_PLAYER_MINUTES', 1440),
    ),
];
