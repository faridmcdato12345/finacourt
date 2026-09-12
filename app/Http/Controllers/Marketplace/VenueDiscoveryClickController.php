<?php

namespace App\Http\Controllers\Marketplace;

use App\Analytics\TrafficAttribution;
use App\Http\Controllers\Controller;
use App\Marketplace\MarketplaceQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class VenueDiscoveryClickController extends Controller
{
    public function __invoke(
        Request $request,
        string $venueSlug,
        MarketplaceQuery $marketplace,
        TrafficAttribution $attribution,
    ): RedirectResponse {
        $venue = $marketplace->venue($venueSlug);
        $context = $request->string('context')->toString();

        if (! in_array($context, ['court_search', 'city_landing', 'sport_landing', 'marketplace_home'], true)) {
            $context = 'marketplace_listing';
        }

        $attribution->marketplaceDiscovery($request, $context);

        return redirect()->route('marketplace.venues.show', $venue->slug);
    }
}
