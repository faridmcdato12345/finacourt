<?php

return [
    'content_security_policy' => env('SECURITY_CSP_ENABLED', env('APP_ENV') === 'production'),
];
