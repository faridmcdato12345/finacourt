<?php

return [
    /*
     * The live demo is intentionally off in production unless it is explicitly
     * enabled. Local, testing, and staging environments can use it immediately.
     */
    'enabled' => filter_var(
        env('INTERACTIVE_DEMO_ENABLED', env('APP_ENV') !== 'production'),
        FILTER_VALIDATE_BOOLEAN,
    ),
    'workspace_lifetime_hours' => (int) env('INTERACTIVE_DEMO_WORKSPACE_LIFETIME_HOURS', 12),
];
