<?php

return [
    'operator_name' => env('LEGAL_OPERATOR_NAME', env('APP_NAME', 'FinACourt')),
    'contact_email' => env('LEGAL_CONTACT_EMAIL', env('MAIL_FROM_ADDRESS', 'hello@example.com')),
    'effective_date' => env('LEGAL_EFFECTIVE_DATE', 'September 2, 2026'),
];
