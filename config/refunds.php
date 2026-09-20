<?php

return [
    // Player-initiated full-refund requests must arrive this many hours before
    // the booked start time. Venue emergency closures use a separate workflow
    // and remain refundable regardless of this cutoff.
    'player_request_cutoff_hours' => max(0, (int) env('PLAYER_REFUND_CUTOFF_HOURS', 24)),

    // A player who completes payment after the normal cutoff gets a short
    // cooling-off period, but never beyond the booked court's start time.
    'new_booking_grace_minutes' => max(0, (int) env('PLAYER_REFUND_GRACE_MINUTES', 15)),
];
