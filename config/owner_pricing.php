<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Public owner pricing
    |--------------------------------------------------------------------------
    |
    | The actual player service fee is read from the effective platform fee
    | rule used by booking checkout. This file holds only the owner-facing list
    | of included tools and the monitored pricing/support contact.
    |
    */
    'features' => [
        'Public venue and court listings',
        'Court times checked before every reservation',
        'One place for courts, schedules, prices, and bookings',
        'See what players search for and which hours are still open',
        'Create open-court deals and see whether they lead to bookings',
        'Invite past customers back only when they agreed to messages',
        'Visibility checklist, QR booking links, and map directions',
        'See visits, booking interest, customers, and confirmed booking amounts',
        'Pay-at-venue and online-payment tracking',
        'Court earnings, payout requests, and downloadable statements',
    ],

    'sales_email' => env('OWNER_SALES_EMAIL', env('MAIL_FROM_ADDRESS', 'hello@example.com')),
];
