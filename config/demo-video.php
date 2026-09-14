<?php

return [
    'allowed_environments' => ['local', 'testing', 'staging'],
    'allow_other_environments' => (bool) env('DEMO_VIDEO_ALLOW_OTHER_ENVIRONMENTS', false),
    'owner_email' => env('DEMO_VIDEO_OWNER_EMAIL', 'demo.video.owner@finacourt.test'),
    'player_email' => env('DEMO_VIDEO_PLAYER_EMAIL', 'demo.video.player@finacourt.test'),
    'account_password' => env('DEMO_VIDEO_ACCOUNT_PASSWORD', 'finacourt-demo-only'),
    'external_booking_url' => env(
        'DEMO_VIDEO_EXTERNAL_URL',
        'https://existing-booking.test:8443/existing-booking.html',
    ),
];
