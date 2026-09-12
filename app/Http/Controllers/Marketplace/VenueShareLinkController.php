<?php

namespace App\Http\Controllers\Marketplace;

use App\Analytics\TrafficAttribution;
use App\Http\Controllers\Controller;
use App\Marketplace\MarketplaceQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class VenueShareLinkController extends Controller
{
    public function __invoke(
        Request $request,
        string $venueSlug,
        MarketplaceQuery $marketplace,
        TrafficAttribution $attribution,
    ): RedirectResponse {
        $venue = $marketplace->venue($venueSlug);
        $attribution->sharedVenueLink($request, $venue->slug);

        return redirect()->route('marketplace.venues.show', $venue->slug);
    }
}
