<?php

return [
    'places' => [
        // The provider adapter is intentionally not enabled in Phase 15 until
        // valid credentials, billing, policy review, and product approval exist.
        'enabled' => (bool) env('GOOGLE_PLACES_ENABLED', false),
        'api_key' => env('GOOGLE_MAPS_API_KEY'),
    ],
    'business_profile' => [
        // Read-only owner-authorized discovery can be enabled after Google has
        // approved this Cloud project for Business Profile API access.
        'enabled' => (bool) env('GOOGLE_BUSINESS_PROFILE_ENABLED', false),
        'client_id' => env('GOOGLE_BUSINESS_PROFILE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_BUSINESS_PROFILE_CLIENT_SECRET'),
        'redirect_uri' => env('GOOGLE_BUSINESS_PROFILE_REDIRECT_URI', env('APP_URL').'/owner/google-business-profile/callback'),
        'scope' => 'https://www.googleapis.com/auth/business.manage',
        'authorization_url' => 'https://accounts.google.com/o/oauth2/v2/auth',
        'token_url' => 'https://oauth2.googleapis.com/token',
        'revoke_url' => 'https://oauth2.googleapis.com/revoke',
        'account_api_url' => 'https://mybusinessaccountmanagement.googleapis.com',
        'information_api_url' => 'https://mybusinessbusinessinformation.googleapis.com',
        'state_ttl_minutes' => 10,
        'max_candidates' => 200,
    ],
];
