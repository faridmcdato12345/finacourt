<?php

return [
    'enabled' => (bool) env('OUTREACH_ENABLED', false),
    'daily_limit' => max(1, (int) env('OUTREACH_DAILY_LIMIT', 20)),
    'followup_1_days' => max(1, (int) env('OUTREACH_FOLLOWUP_1_DAYS', 4)),
    'followup_2_days' => max(1, (int) env('OUTREACH_FOLLOWUP_2_DAYS', 5)),
    'from' => [
        'address' => env('OUTREACH_FROM_ADDRESS', 'support@finacourt.asia'),
        'name' => env('OUTREACH_FROM_NAME', 'Farid - FinACourt'),
    ],
    'reply_to' => env('OUTREACH_REPLY_TO_ADDRESS', env('OUTREACH_FROM_ADDRESS', 'support@finacourt.asia')),
    'owner_overview_url' => env('OUTREACH_OWNER_OVERVIEW_URL', 'https://finacourt.asia/for-court-owners'),
    'timezone' => env('OUTREACH_TIMEZONE', 'Asia/Manila'),
    'google_sheets' => [
        'spreadsheet_id' => env('GOOGLE_SHEETS_SPREADSHEET_ID'),
        'range' => env('GOOGLE_SHEETS_RANGE', 'Sheet1!A:C'),
        'service_account_json' => env('GOOGLE_SERVICE_ACCOUNT_JSON'),
    ],
];
