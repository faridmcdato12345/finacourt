<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Public locality names
    |--------------------------------------------------------------------------
    |
    | Keys are authoritative PSGC codes. Canonical names remain unchanged in
    | storage, filters, routes, and analytics. Only public-facing labels and
    | SEO copy use the display/SEO names below. Add entries explicitly instead
    | of applying a blanket "City of ..." rewrite.
    |
    */
    'locations' => [
        '0730600000' => [
            'canonical_name' => 'City of Cebu',
            'display_name' => 'Cebu City',
            'seo_name' => 'Cebu City',
            'aliases' => ['Cebu', 'Cebu City', 'City of Cebu'],
        ],
        '1030900000' => [
            'canonical_name' => 'City of Iligan',
            'display_name' => 'Iligan City',
            'seo_name' => 'Iligan City',
            'aliases' => ['Iligan', 'Iligan City', 'City of Iligan'],
        ],
        '1130700000' => [
            'canonical_name' => 'City of Davao',
            'display_name' => 'Davao City',
            'seo_name' => 'Davao City',
            'aliases' => ['Davao', 'Davao City', 'City of Davao'],
        ],
        '1903617000' => [
            'canonical_name' => 'City of Marawi',
            'display_name' => 'Marawi City',
            'seo_name' => 'Marawi City',
            'aliases' => ['Marawi', 'Marawi City', 'City of Marawi'],
        ],
    ],
];
