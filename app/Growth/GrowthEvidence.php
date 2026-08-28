<?php

namespace App\Growth;

use App\CustomerReactivation\CustomerBookingHistory;
use App\Enums\AcquisitionSource;
use App\Enums\AnalyticsEventType;
use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\AnalyticsEvent;
use App\Models\Booking;
use App\Models\Organization;
use App\Models\Promotion;
use App\Models\Venue;
use App\Promotions\EmptySlotFinder;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class GrowthEvidence
{
    public function __construct(
        private readonly EmptySlotFinder $emptySlots,
        private readonly CustomerBookingHistory $customerHistory,
    ) {}

    /** @return Collection<int, Venue> */
    public function marketplaceVenues(Organization $organization): Collection
    {
        return Venue::query()
            ->where('organization_id', $organization->getKey())
            ->marketplace()
            ->with('sports:id,name,slug')
            ->orderBy('name')
            ->get(['id', 'organization_id', 'name', 'slug', 'city', 'city_slug', 'province']);
    }

    /** @return Collection<int, array<string, mixed>> */
    public function emptySlots(Organization $organization, CarbonImmutable $at): Collection
    {
        return $this->emptySlots->upcoming(
            $organization,
            horizonDays: (int) config('growth.empty_inventory.horizon_days', 7),
            limit: (int) config('growth.empty_inventory.scan_limit', 250),
            at: $at,
        );
    }

    /** @return Collection<int, array<string, int|string>> */
    public function demandMarkets(
        Organization $organization,
        Collection $venues,
        CarbonImmutable $start,
        CarbonImmutable $end,
    ): Collection {
        $citySlugs = $venues->pluck('city_slug')->filter()->unique()->values();

        if ($citySlugs->isEmpty()) {
            return collect();
        }

        $threshold = max(3, (int) config('demand.minimum_unique_searchers', 3));

        return AnalyticsEvent::query()
            ->where('event_type', AnalyticsEventType::MarketplaceSearch)
            ->where('is_demo', false)
            ->where('occurred_at', '>=', $start)
            ->where('occurred_at', '<', $end)
            ->whereIn('demand_city_slug', $citySlugs)
            ->whereNotNull('demand_sport_slug')
            ->selectRaw('demand_city_slug as city_slug, demand_sport_slug as sport_slug')
            ->selectRaw('COUNT(*) as searches, COUNT(DISTINCT visitor_hash) as unique_searchers')
            ->selectRaw("SUM(CASE WHEN search_outcome = 'results_available' THEN 1 ELSE 0 END) as results_available")
            ->selectRaw("SUM(CASE WHEN search_outcome = 'no_results' THEN 1 ELSE 0 END) as no_results")
            ->selectRaw("SUM(CASE WHEN search_outcome = 'venues_found_no_availability' THEN 1 ELSE 0 END) as no_availability")
            ->groupBy('demand_city_slug', 'demand_sport_slug')
            ->havingRaw('COUNT(DISTINCT visitor_hash) >= ?', [$threshold])
            ->orderByDesc('searches')
            ->limit(100)
            ->get()
            ->map(fn (AnalyticsEvent $row) => [
                'city_slug' => (string) $row->city_slug,
                'sport_slug' => (string) $row->sport_slug,
                'searches' => (int) $row->searches,
                'unique_searchers' => (int) $row->unique_searchers,
                'results_available' => (int) $row->results_available,
                'no_results' => (int) $row->no_results,
                'no_availability' => (int) $row->no_availability,
                'unfulfilled_searches' => (int) $row->no_results + (int) $row->no_availability,
            ]);
    }

    /** @return array{inactive_30: int, inactive_60: int, prior_weekday: int} */
    public function inactiveSegments(Organization $organization): array
    {
        return $this->customerHistory->segmentCounts($organization);
    }

    /** @return Collection<int, array<string, mixed>> */
    public function promotionPerformance(
        Organization $organization,
        CarbonImmutable $start,
        CarbonImmutable $end,
    ): Collection {
        $rows = $this->qualifiedBookings(Booking::query())
            ->where('bookings.organization_id', $organization->getKey())
            ->where('bookings.source', BookingSource::Marketplace)
            ->whereNotNull('bookings.promotion_id')
            ->where('bookings.created_at', '>=', $start)
            ->where('bookings.created_at', '<', $end)
            ->selectRaw('bookings.promotion_id, COUNT(*) as bookings, SUM(bookings.total_amount) as revenue')
            ->selectRaw('MIN(bookings.currency) as currency, COUNT(DISTINCT bookings.currency) as currency_count')
            ->groupBy('bookings.promotion_id')
            ->orderByDesc('bookings')
            ->get()
            ->keyBy('promotion_id');

        if ($rows->isEmpty()) {
            return collect();
        }

        return Promotion::query()
            ->where('organization_id', $organization->getKey())
            ->whereIn('id', $rows->keys())
            ->with('venue:id,name')
            ->get(['id', 'organization_id', 'venue_id', 'title', 'campaign_token', 'status'])
            ->map(function (Promotion $promotion) use ($rows): array {
                $row = $rows->get($promotion->getKey());

                return [
                    'promotion_id' => $promotion->getKey(),
                    'venue_id' => $promotion->venue_id,
                    'venue_name' => $promotion->venue->name,
                    'title' => $promotion->title,
                    'status' => $promotion->status->value,
                    'bookings' => (int) $row->bookings,
                    'revenue' => (float) $row->revenue,
                    'currency' => (string) $row->currency,
                    'single_currency' => (int) $row->currency_count === 1,
                ];
            });
    }

    /** @return Collection<int, array<string, int|float|string>> */
    public function venueConversions(
        Organization $organization,
        Collection $venues,
        CarbonImmutable $start,
        CarbonImmutable $end,
    ): Collection {
        $venueIds = $venues->modelKeys();

        if ($venueIds === []) {
            return collect();
        }

        $views = AnalyticsEvent::query()
            ->where('organization_id', $organization->getKey())
            ->whereIn('venue_id', $venueIds)
            ->where('event_type', AnalyticsEventType::VenueProfileView)
            ->where('is_demo', false)
            ->where('occurred_at', '>=', $start)
            ->where('occurred_at', '<', $end)
            ->selectRaw('venue_id, COUNT(*) as profile_views, COUNT(DISTINCT visitor_hash) as unique_visitors')
            ->groupBy('venue_id')
            ->get()
            ->keyBy('venue_id');
        $bookings = $this->qualifiedBookings(Booking::query())
            ->where('bookings.organization_id', $organization->getKey())
            ->whereIn('bookings.venue_id', $venueIds)
            ->where('bookings.source', BookingSource::Marketplace)
            ->where('bookings.created_at', '>=', $start)
            ->where('bookings.created_at', '<', $end)
            ->selectRaw('bookings.venue_id, COUNT(*) as bookings')
            ->groupBy('bookings.venue_id')
            ->get()
            ->keyBy('venue_id');

        return $venues->map(function (Venue $venue) use ($views, $bookings): array {
            $profileViews = (int) ($views->get($venue->getKey())?->profile_views ?? 0);
            $completed = (int) ($bookings->get($venue->getKey())?->bookings ?? 0);

            return [
                'venue_id' => $venue->getKey(),
                'venue_name' => $venue->name,
                'profile_views' => $profileViews,
                'unique_visitors' => (int) ($views->get($venue->getKey())?->unique_visitors ?? 0),
                'bookings' => $completed,
                'conversion_rate' => $profileViews === 0 ? 0.0 : round(($completed / $profileViews) * 100, 1),
            ];
        });
    }

    /** @return Collection<int, array<string, int|float|string>> */
    public function channelPerformance(
        Organization $organization,
        CarbonImmutable $start,
        CarbonImmutable $end,
    ): Collection {
        $visitors = AnalyticsEvent::query()
            ->where('organization_id', $organization->getKey())
            ->where('event_type', AnalyticsEventType::VenueProfileView)
            ->where('is_demo', false)
            ->whereNotNull('visitor_hash')
            ->where('occurred_at', '>=', $start)
            ->where('occurred_at', '<', $end)
            ->selectRaw('traffic_source, COUNT(*) as profile_views, COUNT(DISTINCT visitor_hash) as unique_visitors')
            ->groupBy('traffic_source')
            ->get()
            ->groupBy(fn (AnalyticsEvent $row) => (AcquisitionSource::tryFrom((string) $row->traffic_source) ?? AcquisitionSource::Unknown)->value)
            ->map(fn (Collection $rows) => [
                'profile_views' => (int) $rows->sum('profile_views'),
                'unique_visitors' => (int) $rows->sum('unique_visitors'),
            ]);
        $bookings = $this->qualifiedBookings(Booking::query())
            ->join('booking_attributions', 'booking_attributions.booking_id', '=', 'bookings.id')
            ->where('bookings.organization_id', $organization->getKey())
            ->where('bookings.source', BookingSource::Marketplace)
            ->where('bookings.created_at', '>=', $start)
            ->where('bookings.created_at', '<', $end)
            ->selectRaw('booking_attributions.attributed_source as source_label, COUNT(*) as bookings')
            ->groupBy('booking_attributions.attributed_source')
            ->get()
            ->keyBy('source_label');

        return $visitors->map(function (array $traffic, string $sourceValue) use ($bookings): array {
            $source = AcquisitionSource::tryFrom($sourceValue) ?? AcquisitionSource::Unknown;
            $qualifiedBookings = (int) ($bookings->get($source->value)?->bookings ?? 0);
            $uniqueVisitors = $traffic['unique_visitors'];

            return [
                'source' => $source->value,
                'label' => $source->label(),
                'profile_views' => $traffic['profile_views'],
                'unique_visitors' => $uniqueVisitors,
                'bookings' => $qualifiedBookings,
                'conversion_rate' => $uniqueVisitors === 0
                    ? 0.0
                    : round(($qualifiedBookings / $uniqueVisitors) * 100, 1),
            ];
        })->values();
    }

    /** @param Builder<Booking> $query
     * @return Builder<Booking>
     */
    private function qualifiedBookings(Builder $query): Builder
    {
        return $query
            ->where('bookings.status', BookingStatus::Confirmed)
            ->where(function (Builder $query): void {
                $query->whereNull('bookings.payment_status')
                    ->orWhereNotIn('bookings.payment_status', [
                        PaymentStatus::Failed,
                        PaymentStatus::Cancelled,
                        PaymentStatus::Refunded,
                    ]);
            });
    }
}
