<?php

namespace App\Http\Controllers\Marketplace;

use App\Analytics\TrafficAttribution;
use App\Enums\VisibilityLinkDestination;
use App\Http\Controllers\Controller;
use App\Marketplace\MarketplaceQuery;
use App\Models\VisibilityLink;
use App\Promotions\PromotionMarketplace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VisibilityLinkController extends Controller
{
    public function __invoke(
        Request $request,
        VisibilityLink $visibilityLink,
        MarketplaceQuery $marketplace,
        PromotionMarketplace $promotions,
        TrafficAttribution $attribution,
    ): RedirectResponse {
        abort_unless($visibilityLink->is_active, 404);
        $venue = $marketplace->venue($visibilityLink->venue()->value('slug'));
        abort_unless($venue->organization_id === $visibilityLink->organization_id, 404);

        $attribution->visibilityLink($request, $visibilityLink);
        VisibilityLink::query()->whereKey($visibilityLink->getKey())->update([
            'visits_count' => DB::raw('visits_count + 1'),
            'last_visited_at' => now(),
        ]);

        $url = route('marketplace.venues.show', $venue->slug);

        if ($visibilityLink->destination === VisibilityLinkDestination::Booking) {
            $url .= '#availability';
        }

        if ($visibilityLink->destination === VisibilityLinkDestination::Promotion) {
            $promotion = $promotions->forVenue($venue)
                ->firstWhere('id', $visibilityLink->promotion_id);

            if ($promotion !== null) {
                $url = route('marketplace.venues.show', [
                    'venueSlug' => $venue->slug,
                    ...$promotion->marketplaceParameters(),
                ]);
            }
        }

        return redirect()->away($url);
    }
}
