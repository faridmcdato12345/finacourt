<?php

namespace App\Http\Controllers\Marketplace;

use App\Analytics\AnalyticsRecorder;
use App\Http\Controllers\Controller;
use App\Marketplace\MarketplaceQuery;
use App\Models\VenueDirectoryListing;
use App\Promotions\PromotionTracker;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(
        Request $request,
        MarketplaceQuery $marketplace,
        PromotionTracker $tracker,
        AnalyticsRecorder $analytics,
    ): View {
        $venues = $marketplace->featured();
        $featuredPromotion = $marketplace->featuredPromotion();
        $directoryListings = VenueDirectoryListing::query()
            ->discoverable()
            ->whereHas('sports', fn ($query) => $query->where('is_active', true))
            ->with(['sports' => fn ($query) => $query
                ->where('is_active', true)
                ->orderBy('name')])
            ->orderByDesc('last_verified_at')
            ->orderByDesc('id')
            ->limit(6)
            ->get(['id', 'slug', 'name', 'city', 'province', 'last_verified_at']);
        $analytics->recordVenueImpressions($request, $venues);
        $tracker->recordImpressions(
            $request,
            $venues
                ->map(fn ($venue) => $venue->relationLoaded('marketplacePromotion')
                    ? $venue->getRelation('marketplacePromotion')
                    : null)
                ->when($featuredPromotion, fn ($promotions) => $promotions->push($featuredPromotion))
                ->filter()
                ->unique('id')
                ->values(),
        );

        return view('marketplace.home', [
            'venues' => $venues,
            'directoryListings' => $directoryListings,
            'featuredPromotion' => $featuredPromotion,
            'socialProof' => $marketplace->playerSocialProof(),
            'cities' => $marketplace->cities(),
            'sports' => $marketplace->sports(),
            'seo' => [
                'title' => 'Find sports courts near you',
                'description' => 'Discover real local sports venues, compare courts and hourly prices, and preview availability without installing an app.',
                'canonical' => route('marketplace.home'),
                'robots' => 'index,follow',
                'type' => 'website',
            ],
            'structuredData' => [],
        ]);
    }
}
