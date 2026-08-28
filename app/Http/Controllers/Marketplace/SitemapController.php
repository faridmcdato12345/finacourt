<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Marketplace\MarketplaceQuery;
use App\Models\Sport;
use App\Models\VenueDirectoryListing;
use App\Promotions\PromotionMarketplace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function __invoke(MarketplaceQuery $marketplace, PromotionMarketplace $promotions): Response
    {
        $combinations = Sport::query()
            ->where('is_active', true)
            ->whereHas('resources', fn (Builder $query) => $query
                ->marketplace()
                ->whereHas('venue', fn (Builder $query) => $query->marketplace()))
            ->with(['resources' => fn ($query) => $query
                ->marketplace()
                ->whereHas('venue', fn (Builder $query) => $query->marketplace())
                ->with('venue:id,city_slug')])
            ->get(['id', 'slug'])
            ->flatMap(fn (Sport $sport) => $sport->resources
                ->pluck('venue.city_slug')
                ->filter()
                ->unique()
                ->map(fn (string $citySlug) => ['sport' => $sport->slug, 'city' => $citySlug]))
            ->unique(fn (array $item) => $item['sport'].'|'.$item['city'])
            ->values();

        return response()->view('marketplace.sitemap', [
            'venues' => $marketplace->sitemapVenues(),
            'cities' => $marketplace->cities(),
            'combinations' => $combinations,
            'hasDeals' => $promotions->hasDeals(),
            'directoryListings' => VenueDirectoryListing::query()
                ->discoverable()
                ->whereHas('sports', fn (Builder $query) => $query->where('is_active', true))
                ->orderBy('id')
                ->get(['id', 'slug', 'updated_at']),
        ])->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
