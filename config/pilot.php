<?php

return [
    // The seeder also refuses to run outside local/testing, even if this is
    // accidentally enabled in a production environment.
    'demo_seed_enabled' => (bool) env('PILOT_DEMO_SEED', false),
];
