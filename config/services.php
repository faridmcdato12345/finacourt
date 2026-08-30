<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'google' => [
        'client_id' => env('GOOGLE_AUTH_CLIENT_ID'),
        'client_secret' => env('GOOGLE_AUTH_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_AUTH_REDIRECT_URI', env('APP_URL').'/auth/google/callback'),
    ],

    'facebook' => [
        'client_id' => env('FACEBOOK_AUTH_CLIENT_ID'),
        'client_secret' => env('FACEBOOK_AUTH_CLIENT_SECRET'),
        'redirect' => env('FACEBOOK_AUTH_REDIRECT_URI', env('APP_URL').'/auth/facebook/callback'),
    ],

    'apple' => [
        'client_id' => env('APPLE_AUTH_CLIENT_ID'),
        'client_secret' => env('APPLE_AUTH_CLIENT_SECRET'),
        'redirect' => env('APPLE_AUTH_REDIRECT_URI', env('APP_URL').'/auth/apple/callback'),
        'key_id' => env('APPLE_AUTH_KEY_ID'),
        'team_id' => env('APPLE_AUTH_TEAM_ID'),
        'private_key' => env('APPLE_AUTH_PRIVATE_KEY'),
        'passphrase' => env('APPLE_AUTH_PRIVATE_KEY_PASSPHRASE'),
    ],

];
