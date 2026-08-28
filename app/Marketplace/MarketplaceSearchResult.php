<?php

namespace App\Marketplace;

use App\Enums\DemandSearchOutcome;
use App\Models\Venue;
use Illuminate\Support\Collection;

readonly class MarketplaceSearchResult
{
    /** @param Collection<int, Venue> $venues */
    public function __construct(
        public Collection $venues,
        public int $matchingVenueCount,
        public int $availableVenueCount,
        public DemandSearchOutcome $outcome,
    ) {}
}
