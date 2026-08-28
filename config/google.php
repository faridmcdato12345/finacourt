<?php

return [
    'places' => [
        // The provider adapter is intentionally not enabled in Phase 15 until
        // valid credentials, billing, policy review, and product approval exist.
        'enabled' => (bool) env('GOOGLE_PLACES_ENABLED', false),
        'api_key' => env('GOOGLE_MAPS_API_KEY'),
    ],
    'business_profile' => [
        // OAuth and profile mutation remain disabled. These placeholders make
        // future deployment requirements explicit without inventing behavior.
        'enabled' => (bool) env('GOOGLE_BUSINESS_PROFILE_ENABLED', false),
        'client_id' => env('GOOGLE_BUSINESS_PROFILE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_BUSINESS_PROFILE_CLIENT_SECRET'),
        'redirect_uri' => env('GOOGLE_BUSINESS_PROFILE_REDIRECT_URI'),
    ],
];
