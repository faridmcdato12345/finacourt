<?php

namespace App\Analytics;

use App\Enums\AnalyticsEventType;
use App\Enums\MembershipRole;
use App\Models\AnalyticsEvent;
use App\Models\CourtResource;
use App\Models\Organization;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Builder;

class PlatformAcquisitionReport
{
    public function __construct(private readonly DemandReport $demandReport) {}

    /** @return array<string, mixed> */
    public function generate(AnalyticsPeriod $period): array
    {
        $demand = $this->demandReport->platform($period);
        $eventCounts = $this->events($period)
            ->selectRaw('event_type, COUNT(*) as aggregate')
            ->groupBy('event_type')
            ->pluck('aggregate', 'event_type');
        $searches = $demand['metrics']['searches'];
        $demandSegments = collect($demand['markets'])->map(fn (array $market) => [
            ...$market,
            'high_intent_searches' => $market['date_time_searches'],
            'zero_results' => $market['no_results'],
        ])->all();

        return [
            'metrics' => [
                'searches' => $searches,
                'unique_searchers' => $demand['metrics']['unique_searchers'],
                'high_intent_searches' => $demand['metrics']['date_time_searches'],
                'zero_result_searches' => $demand['metrics']['no_results'],
                'no_availability_searches' => $demand['metrics']['no_availability'],
                'unfulfilled_searches' => $demand['metrics']['unfulfilled_searches'],
                'search_coverage_rate' => $demand['metrics']['coverage_rate'],
            ],
            'funnel' => [
                ['label' => 'Marketplace searches', 'value' => $searches],
                ['label' => 'Venue impressions', 'value' => (int) ($eventCounts[AnalyticsEventType::VenueImpression->value] ?? 0)],
                ['label' => 'Profile views', 'value' => (int) ($eventCounts[AnalyticsEventType::VenueProfileView->value] ?? 0)],
                ['label' => 'Availability views', 'value' => (int) ($eventCounts[AnalyticsEventType::AvailabilityView->value] ?? 0)],
                ['label' => 'Booking starts', 'value' => (int) ($eventCounts[AnalyticsEventType::BookingStart->value] ?? 0)],
                ['label' => 'Completed bookings', 'value' => (int) ($eventCounts[AnalyticsEventType::CompletedBooking->value] ?? 0)],
            ],
            'supply' => $this->supplySnapshot(),
            'demand' => $demand,
            'demand_segments' => $demandSegments,
            'prospect_venues' => $this->prospectVenues($period),
        ];
    }

    /** @return Builder<AnalyticsEvent> */
    private function events(AnalyticsPeriod $period): Builder
    {
        return AnalyticsEvent::query()
            ->where('is_demo', false)
            ->where('occurred_at', '>=', $period->utcStart)
            ->where('occurred_at', '<', $period->utcEnd);
    }

    /** @return array<string, int> */
    private function supplySnapshot(): array
    {
        return [
            'registered_owner_organizations' => Organization::query()
                ->whereHas('memberships', fn (Builder $query) => $query->where('role', MembershipRole::Owner))
                ->count(),
            'claimed_venues' => Venue::query()->whereNotNull('claimed_at')->count(),
            'unclaimed_venues' => Venue::query()->whereNull('claimed_at')->count(),
            'published_venues' => Venue::query()->marketplace()->count(),
            'active_courts' => CourtResource::query()
                ->marketplace()
                ->whereHas('venue', fn (Builder $query) => $query->marketplace())
                ->count(),
            'active_cities' => Venue::query()
                ->marketplace()
                ->distinct('city_slug')
                ->count('city_slug'),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function prospectVenues(AnalyticsPeriod $period): array
    {
        $venues = Venue::query()
            ->whereNull('claimed_at')
            ->with('sports:id,name')
            ->withCount([
                'resources as active_resources_count' => fn (Builder $query) => $query->where('is_active', true),
            ])
            ->limit(100)
            ->get(['id', 'name', 'slug', 'city', 'province', 'is_published']);

        if ($venues->isEmpty()) {
            return [];
        }

        $eventRows = $this->events($period)
            ->whereIn('venue_id', $venues->modelKeys())
            ->selectRaw('venue_id, COUNT(DISTINCT visitor_hash) as unique_visitors')
            ->selectRaw('SUM(CASE WHEN event_type = ? THEN 1 ELSE 0 END) as impressions', [AnalyticsEventType::VenueImpression->value])
            ->selectRaw('SUM(CASE WHEN event_type = ? THEN 1 ELSE 0 END) as profile_views', [AnalyticsEventType::VenueProfileView->value])
            ->selectRaw('SUM(CASE WHEN event_type = ? THEN 1 ELSE 0 END) as availability_views', [AnalyticsEventType::AvailabilityView->value])
            ->groupBy('venue_id')
            ->get()
            ->keyBy('venue_id');

        return $venues
            ->map(function (Venue $venue) use ($eventRows): array {
                $events = $eventRows->get($venue->getKey());
                $impressions = (int) ($events?->impressions ?? 0);
                $profileViews = (int) ($events?->profile_views ?? 0);
                $availabilityViews = (int) ($events?->availability_views ?? 0);

                return [
                    'id' => $venue->getKey(),
                    'name' => $venue->name,
                    'slug' => $venue->slug,
                    'location' => "{$venue->city}, {$venue->province}",
                    'sports' => $venue->sports->pluck('name')->values()->all(),
                    'active_courts' => (int) $venue->active_resources_count,
                    'listing_state' => $venue->is_published ? 'Public listing' : 'Research only',
                    'unique_visitors' => (int) ($events?->unique_visitors ?? 0),
                    'impressions' => $impressions,
                    'profile_views' => $profileViews,
                    'availability_views' => $availabilityViews,
                    'intent_score' => $impressions + ($profileViews * 2) + ($availabilityViews * 4),
                ];
            })
            ->sortByDesc('intent_score')
            ->values()
            ->take(20)
            ->all();
    }
}
