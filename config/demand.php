<?php

return [
    // Owner demand views are suppressed until this many distinct anonymous
    // sessions exist in the selected market and period. Platform admins only
    // receive aggregates, but are not subject to owner-facing suppression.
    'minimum_unique_searchers' => max(3, (int) env('DEMAND_MINIMUM_UNIQUE_SEARCHERS', 3)),
];
