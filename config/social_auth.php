<?php

return [
    'providers' => [
        'google' => [
            'label' => 'Google',
            'enabled' => env('SOCIAL_AUTH_GOOGLE_ENABLED', false),
        ],
        'facebook' => [
            'label' => 'Facebook',
            'enabled' => env('SOCIAL_AUTH_FACEBOOK_ENABLED', false),
        ],
        'apple' => [
            'label' => 'Apple',
            'enabled' => env('SOCIAL_AUTH_APPLE_ENABLED', false),
        ],
    ],
];
