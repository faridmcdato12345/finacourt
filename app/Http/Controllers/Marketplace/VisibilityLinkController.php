<?php

namespace App\Http\Controllers\Marketplace;

use App\Analytics\AnalyticsRecorder;
use App\Analytics\TrafficAttribution;
use App\BookingLinks\ExternalBookingUrl;
use App\Enums\VisibilityLinkDestination;
use App\Http\Controllers\Controller;
use App\Marketplace\MarketplaceQuery;
use App\Models\VisibilityLink;
use App\Promotions\PromotionMarketplace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class VisibilityLinkController extends Controller
{
    public function __invoke(
        Request $request,
        VisibilityLink $visibilityLink,
        MarketplaceQuery $marketplace,
        PromotionMarketplace $promotions,
        TrafficAttribution $attribution,
        AnalyticsRecorder $analytics,
        ExternalBookingUrl $externalUrls,
    ): RedirectResponse {
        abort_unless($visibilityLink->is_active, 404);

        if ($visibilityLink->destination === VisibilityLinkDestination::ExternalBooking) {
            return $this->external($request, $visibilityLink, $analytics, $externalUrls);
        }

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

    private function external(
        Request $request,
        VisibilityLink $link,
        AnalyticsRecorder $analytics,
        ExternalBookingUrl $externalUrls,
    ): RedirectResponse {
        $link->loadMissing(['venue', 'externalBookingDestination']);
        $destination = $link->externalBookingDestination;
        abort_unless($destination !== null
            && $destination->is_active
            && $link->organization_id === $link->venue->organization_id
            && $destination->organization_id === $link->organization_id
            && $destination->venue_id === $link->venue_id, 410);

        try {
            $url = $externalUrls->normalize($destination->destination_url);
        } catch (InvalidArgumentException) {
            abort(410);
        }

        DB::transaction(function () use ($request, $link, $analytics): void {
            if ($analytics->recordExternalBookingLinkClick($request, $link)) {
                VisibilityLink::query()->whereKey($link->getKey())->update([
                    'visits_count' => DB::raw('visits_count + 1'),
                    'last_visited_at' => now(),
                ]);
            }
        });

        return redirect()->away($url, 302);
    }
}
