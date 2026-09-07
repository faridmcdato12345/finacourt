<?php

namespace App\Http\Controllers\Marketplace;

use App\Analytics\AnalyticsRecorder;
use App\Enums\ResourceSetting;
use App\Http\Controllers\Controller;
use App\Marketplace\LocationLandingSummary;
use App\Marketplace\MarketplaceQuery;
use App\Marketplace\StructuredData;
use App\Models\Sport;
use App\Models\Venue;
use App\Promotions\PromotionTracker;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DiscoveryController extends Controller
{
    public function index(
        Request $request,
        MarketplaceQuery $marketplace,
        PromotionTracker $tracker,
        AnalyticsRecorder $analytics,
    ): View {
        $filters = $this->filters($request);
        $hasFilters = collect($filters)->except('duration_minutes')->filter()->isNotEmpty();
        $search = $marketplace->searchWithDemand($filters);
        $venues = $search->venues;
        $analytics->recordMarketplaceSearch($request, $filters, $search);
        $analytics->recordVenueImpressions($request, $venues);
        $this->trackPromotions($request, $venues, $tracker);

        return view('marketplace.discovery', [
            'venues' => $venues,
            'cities' => $marketplace->cities(),
            'sports' => $marketplace->sports(),
            'filters' => $filters,
            'settings' => ResourceSetting::cases(),
            'eyebrow' => 'Court discovery',
            'heading' => 'Find a court that fits your game',
            'introduction' => 'Browse published facilities with active courts, real hourly prices, and availability powered by each venue’s live schedule.',
            'breadcrumbs' => [],
            'lockedCity' => false,
            'lockedSport' => false,
            'locationSummary' => null,
            'relatedCities' => collect(),
            'seo' => [
                'title' => 'Discover sports courts and venues',
                'description' => 'Browse sports courts by city, sport, setting, price, date, and time.',
                'canonical' => route('marketplace.courts.index'),
                'robots' => $hasFilters ? 'noindex,follow' : 'index,follow',
                'type' => 'website',
            ],
            'structuredData' => [],
        ]);
    }

    public function city(
        Request $request,
        string $citySlug,
        MarketplaceQuery $marketplace,
        LocationLandingSummary $locationLandingSummary,
        StructuredData $structuredData,
        PromotionTracker $tracker,
        AnalyticsRecorder $analytics,
    ): View {
        $filters = ['city' => $citySlug, 'duration_minutes' => 60];
        $search = $marketplace->searchWithDemand($filters);
        $venues = $search->venues;
        abort_if($venues->isEmpty(), 404);
        $analytics->recordMarketplaceSearch($request, $filters, $search, 'city_landing');
        $analytics->recordVenueImpressions($request, $venues);
        $this->trackPromotions($request, $venues, $tracker);
        $location = $venues->first();
        $cityName = $location->publicCitySeoName();
        $canonical = route('marketplace.courts.city', $citySlug);
        $cities = $marketplace->cities();
        $summary = $locationLandingSummary->fromVenues(
            $venues,
            $cityName,
            $location->province,
        );
        $relatedCities = $cities
            ->filter(fn (Venue $city) => $city->city_slug !== $citySlug
                && filled($location->province)
                && $city->province === $location->province)
            ->unique('city_slug')
            ->take(6)
            ->values();

        return view('marketplace.discovery', [
            'venues' => $venues,
            'cities' => $cities,
            'sports' => $marketplace->sports(),
            'filters' => ['city' => $citySlug, 'duration_minutes' => 60],
            'settings' => ResourceSetting::cases(),
            'eyebrow' => $location->province,
            'heading' => "Sports Courts in {$cityName}",
            'introduction' => $summary['introduction'],
            'breadcrumbs' => [
                ['name' => 'Courts', 'url' => route('marketplace.courts.index')],
                ['name' => $cityName, 'url' => $canonical],
            ],
            'lockedCity' => true,
            'lockedSport' => false,
            'locationSummary' => $summary,
            'relatedCities' => $relatedCities,
            'seo' => [
                'title' => "Sports Courts in {$cityName}",
                'document_title' => "Sports Courts in {$cityName} | Find & Book Courts | FinACourt",
                'description' => $summary['meta_description'],
                'canonical' => $canonical,
                'robots' => 'index,follow',
                'type' => 'website',
            ],
            'structuredData' => [
                $structuredData->breadcrumbs([
                    ['name' => 'Home', 'url' => route('marketplace.home')],
                    ['name' => 'Courts', 'url' => route('marketplace.courts.index')],
                    ['name' => $cityName, 'url' => $canonical],
                ]),
                $structuredData->venueList($venues, "Sports courts in {$cityName}"),
            ],
        ]);
    }

    public function sportCity(
        Request $request,
        string $sportSlug,
        string $citySlug,
        MarketplaceQuery $marketplace,
        StructuredData $structuredData,
        PromotionTracker $tracker,
        AnalyticsRecorder $analytics,
    ): View {
        $sport = Sport::query()->where('slug', $sportSlug)->where('is_active', true)->firstOrFail();
        $filters = [
            'city' => $citySlug,
            'sport' => $sportSlug,
            'duration_minutes' => 60,
        ];
        $search = $marketplace->searchWithDemand($filters);
        $venues = $search->venues;
        abort_if($venues->isEmpty(), 404);
        $analytics->recordMarketplaceSearch($request, $filters, $search, 'sport_city_landing');
        $analytics->recordVenueImpressions($request, $venues);
        $this->trackPromotions($request, $venues, $tracker);
        $location = $venues->first();
        $cityName = $location->publicCitySeoName();
        $sportName = $sport->name;
        $sportNameLower = str($sportName)->lower();
        $canonical = route('marketplace.courts.sport-city', [$sportSlug, $citySlug]);

        return view('marketplace.discovery', [
            'venues' => $venues,
            'cities' => $marketplace->cities(),
            'sports' => $marketplace->sports(),
            'filters' => ['city' => $citySlug, 'sport' => $sportSlug, 'duration_minutes' => 60],
            'settings' => ResourceSetting::cases(),
            'eyebrow' => $location->province,
            'heading' => "{$sportName} Courts in {$cityName}",
            'introduction' => "Looking for a {$sportNameLower} court in {$cityName}? Compare local venues, prices, court settings, and availability, then book online with FinACourt.",
            'breadcrumbs' => [
                ['name' => 'Courts', 'url' => route('marketplace.courts.index')],
                ['name' => $cityName, 'url' => route('marketplace.courts.city', $citySlug)],
                ['name' => $sportName, 'url' => $canonical],
            ],
            'lockedCity' => true,
            'lockedSport' => true,
            'locationSummary' => null,
            'relatedCities' => collect(),
            'seo' => [
                'title' => "{$sportName} Courts in {$cityName}",
                'document_title' => "{$sportName} Courts in {$cityName} | Find & Book | FinACourt",
                'description' => "Find {$sportNameLower} courts in {$cityName}. Compare local venues, prices, schedules, and availability, then book online with FinACourt.",
                'canonical' => $canonical,
                'robots' => 'index,follow',
                'type' => 'website',
            ],
            'structuredData' => [$structuredData->breadcrumbs([
                ['name' => 'Home', 'url' => route('marketplace.home')],
                ['name' => 'Courts', 'url' => route('marketplace.courts.index')],
                ['name' => $cityName, 'url' => route('marketplace.courts.city', $citySlug)],
                ['name' => $sportName, 'url' => $canonical],
            ])],
        ]);
    }

    /** @return array<string, mixed> */
    private function filters(Request $request): array
    {
        foreach (['city', 'sport'] as $filter) {
            if ($request->input($filter) === '__court_select_empty__') {
                $request->merge([$filter => null]);
            }
        }

        $validated = $request->validate([
            'city' => ['nullable', 'string', 'max:255', 'alpha_dash:ascii'],
            'sport' => ['nullable', 'string', 'max:255', 'alpha_dash:ascii'],
            'setting' => ['nullable', Rule::enum(ResourceSetting::class)],
            'max_price' => ['nullable', 'numeric', 'between:0,999999.99'],
            'date' => ['nullable', 'date_format:Y-m-d', 'required_with:start_time'],
            'start_time' => ['nullable', 'date_format:H:i', 'required_with:date'],
            'duration_minutes' => ['nullable', 'integer', Rule::in([30, 60, 90, 120])],
        ]);
        $validated['duration_minutes'] ??= 60;

        return $validated;
    }

    /** @param Collection<int, Venue> $venues */
    private function trackPromotions(Request $request, Collection $venues, PromotionTracker $tracker): void
    {
        $tracker->recordImpressions(
            $request,
            $venues
                ->map(fn (Venue $venue) => $venue->relationLoaded('marketplacePromotion')
                    ? $venue->getRelation('marketplacePromotion')
                    : null)
                ->filter()
                ->unique('id')
                ->values(),
        );
    }
}
