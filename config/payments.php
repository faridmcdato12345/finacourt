<?php

return [
    'default' => env('PAYMENT_PROVIDER', 'manual'),

    'providers' => [
        'manual' => [
            'enabled' => true,
        ],

        'paymongo' => [
            'enabled' => filter_var(env('PAYMONGO_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
            'mode' => env('PAYMONGO_MODE', 'test'),
            'api_base_url' => env('PAYMONGO_API_BASE_URL', 'https://api.paymongo.com'),
            'secret_key' => env('PAYMONGO_SECRET_KEY'),
            'webhook_secret' => env('PAYMONGO_WEBHOOK_SECRET'),
            'payment_method_types' => array_values(array_filter(array_map(
                'trim',
                explode(',', (string) env('PAYMONGO_PAYMENT_METHOD_TYPES', 'card,gcash,qrph')),
            ))),
            'send_email_receipt' => filter_var(env('PAYMONGO_SEND_EMAIL_RECEIPT', true), FILTER_VALIDATE_BOOLEAN),
            'pass_on_fees' => filter_var(env('PAYMONGO_PASS_ON_FEES', false), FILTER_VALIDATE_BOOLEAN),
            'description' => env('PAYMONGO_CHECKOUT_DESCRIPTION', 'FinACourt court reservation'),
            'signature_tolerance_seconds' => (int) env('PAYMONGO_WEBHOOK_TOLERANCE_SECONDS', 300),
        ],
    ],
];
