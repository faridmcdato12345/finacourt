<?php

namespace App\Analytics;

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
use App\Models\VenueDirectoryListing;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class AnalyticsReport
{
    /** @return array<string, mixed> */
    public function generate(
        AnalyticsPeriod $period,
        ?Organization $organization = null,
        ?Venue $venue = null,
    ): array {
        $events = $this->eventQuery($period, $organization, $venue);
        $bookings = $this->bookingQuery($period, $organization, $venue);
        $qualified = $this->qualifiedBookings(clone $bookings);
        $profileViews = (clone $events)->where('event_type', AnalyticsEventType::VenueProfileView)->count();
        $completedBookings = (clone $qualified)->count();
        $revenue = (string) ((clone $qualified)->sum('total_amount') ?: 0);
        [$newCustomers, $returningCustomers] = $this->customerCounts(
            clone $qualified,
            $period,
            $organization,
        );
        $trafficSources = $this->trafficSources(clone $qualified, $period, $organization);

        return [
            'period' => ['from' => $period->from, 'to' => $period->to],
            'metrics' => [
                'impressions' => (clone $events)->where('event_type', AnalyticsEventType::VenueImpression)->count(),
                'unique_visitors' => (clone $events)->whereNotNull('visitor_hash')->distinct('visitor_hash')->count('visitor_hash'),
                'profile_views' => $profileViews,
                'availability_views' => (clone $events)->where('event_type', AnalyticsEventType::AvailabilityView)->count(),
                'booking_starts' => (clone $bookings)->count(),
                'completed_bookings' => $completedBookings,
                'conversion_rate' => $profileViews === 0
                    ? 0.0
                    : round(($completedBookings / $profileViews) * 100, 1),
                'new_customers' => $newCustomers,
                'returning_customers' => $returningCustomers,
                'booking_revenue' => number_format((float) $revenue, 2, '.', ''),
            ],
            'traffic_sources' => $trafficSources,
            'acquisition_metrics' => $this->acquisitionMetrics($trafficSources),
            'promotions' => $this->promotionPerformance($period, $organization, $venue),
            'organizations' => $organization === null
                ? $this->organizationPerformance($period)
                : [],
            'venues' => $organization === null
                ? $this->venuePerformance($period)
                : [],
        ];
    }

    /** @return Builder<AnalyticsEvent> */
    private function eventQuery(
        AnalyticsPeriod $period,
        ?Organization $organization,
        ?Venue $venue,
    ): Builder {
        return AnalyticsEvent::query()
            ->where('occurred_at', '>=', $period->utcStart)
            ->where('occurred_at', '<', $period->utcEnd)
            ->when($organization, fn (Builder $query) => $query->where('organization_id', $organization->getKey()))
            ->when($venue, fn (Builder $query) => $query->where('venue_id', $venue->getKey()));
    }

    /** @return Builder<Booking> */
    private function bookingQuery(
        AnalyticsPeriod $period,
        ?Organization $organization,
        ?Venue $venue,
    ): Builder {
        return Booking::query()
            ->where('bookings.source', BookingSource::Marketplace)
            ->where('bookings.created_at', '>=', $period->utcStart)
            ->where('bookings.created_at', '<', $period->utcEnd)
            ->when($organization, fn (Builder $query) => $query->where('bookings.organization_id', $organization->getKey()))
            ->when($venue, fn (Builder $query) => $query->where('bookings.venue_id', $venue->getKey()));
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

    /** @param Builder<Booking> $qualified
     * @return array{int, int}
     */
    private function customerCounts(
        Builder $qualified,
        AnalyticsPeriod $period,
        ?Organization $organization,
    ): array {
        $currentCustomers = $qualified
            ->whereNotNull('bookings.player_user_id')
            ->get(['bookings.organization_id', 'bookings.player_user_id'])
            ->unique(fn (Booking $booking) => "{$booking->organization_id}:{$booking->player_user_id}")
            ->values();

        if ($currentCustomers->isEmpty()) {
            return [0, 0];
        }

        $organizationIds = $organization
            ? collect([$organization->getKey()])
            : $currentCustomers->pluck('organization_id')->unique()->values();
        $playerIds = $currentCustomers->pluck('player_user_id')->unique()->values();
        $firstBookings = $this->qualifiedBookings(Booking::query()
            ->where('bookings.source', BookingSource::Marketplace)
            ->whereIn('bookings.organization_id', $organizationIds)
            ->whereIn('bookings.player_user_id', $playerIds))
            ->selectRaw('bookings.organization_id, bookings.player_user_id, MIN(bookings.created_at) as first_at')
            ->groupBy('bookings.organization_id', 'bookings.player_user_id')
            ->get()
            ->keyBy(fn (Booking $booking) => "{$booking->organization_id}:{$booking->player_user_id}");

        $new = $currentCustomers->filter(function (Booking $booking) use ($firstBookings, $period): bool {
            $first = $firstBookings->get("{$booking->organization_id}:{$booking->player_user_id}");

            return $first !== null && CarbonImmutable::parse($first->first_at, 'UTC')->greaterThanOrEqualTo($period->utcStart);
        })->count();

        return [$new, $currentCustomers->count() - $new];
    }

    /** @param Builder<Booking> $qualified
     * @return array<int, array<string, mixed>>
     */
    private function trafficSources(
        Builder $qualified,
        AnalyticsPeriod $period,
        ?Organization $organization,
    ): array {
        $source = $this->sourceExpression();
        $newCustomers = $this->newCustomerSourceCounts(clone $qualified, $period, $organization);

        return $qualified
            ->leftJoin('booking_attributions', 'booking_attributions.booking_id', '=', 'bookings.id')
            ->selectRaw("{$source} as source_label, COUNT(*) as bookings, SUM(bookings.total_amount) as revenue")
            ->groupByRaw($source)
            ->orderByDesc('bookings')
            ->get()
            ->map(function (Booking $row) use ($newCustomers): array {
                $source = AcquisitionSource::tryFrom($row->source_label) ?? AcquisitionSource::Unknown;

                return [
                    'source' => $source->value,
                    'label' => $source->label(),
                    'bookings' => (int) $row->bookings,
                    'new_customers' => (int) ($newCustomers[$source->value] ?? 0),
                    'revenue' => number_format((float) $row->revenue, 2, '.', ''),
                ];
            })->all();
    }

    /** @param Builder<Booking> $qualified
     * @return array<string, int>
     */
    private function newCustomerSourceCounts(
        Builder $qualified,
        AnalyticsPeriod $period,
        ?Organization $organization,
    ): array {
        $source = $this->sourceExpression();
        $current = $qualified
            ->whereNotNull('bookings.player_user_id')
            ->leftJoin('booking_attributions', 'booking_attributions.booking_id', '=', 'bookings.id')
            ->selectRaw("bookings.organization_id, bookings.player_user_id, bookings.created_at, {$source} as source_label")
            ->orderBy('bookings.created_at')
            ->orderBy('bookings.id')
            ->get()
            ->unique(fn (Booking $booking) => "{$booking->organization_id}:{$booking->player_user_id}")
            ->values();

        if ($current->isEmpty()) {
            return [];
        }

        $organizationIds = $organization
            ? collect([$organization->getKey()])
            : $current->pluck('organization_id')->unique()->values();
        $firstBookings = $this->qualifiedBookings(Booking::query()
            ->where('bookings.source', BookingSource::Marketplace)
            ->whereIn('bookings.organization_id', $organizationIds)
            ->whereIn('bookings.player_user_id', $current->pluck('player_user_id')->unique()))
            ->selectRaw('bookings.organization_id, bookings.player_user_id, MIN(bookings.created_at) as first_at')
            ->groupBy('bookings.organization_id', 'bookings.player_user_id')
            ->get()
            ->keyBy(fn (Booking $booking) => "{$booking->organization_id}:{$booking->player_user_id}");

        return $current
            ->filter(function (Booking $booking) use ($firstBookings, $period): bool {
                $first = $firstBookings->get("{$booking->organization_id}:{$booking->player_user_id}");

                return $first !== null
                    && CarbonImmutable::parse($first->first_at, 'UTC')->greaterThanOrEqualTo($period->utcStart);
            })
            ->countBy(fn (Booking $booking) => $booking->source_label)
            ->map(fn (int $count) => $count)
            ->all();
    }

    private function sourceExpression(): string
    {
        $recognized = collect(AcquisitionSource::cases())
            ->map(fn (AcquisitionSource $source) => "'{$source->value}'")
            ->implode(', ');
        $promotion = AcquisitionSource::MarketplacePromotion->value;
        $unknown = AcquisitionSource::Unknown->value;
        $direct = AcquisitionSource::Direct->value;

        return <<<SQL
            COALESCE(
                booking_attributions.attributed_source,
                CASE
                    WHEN bookings.traffic_source = 'promotion' THEN '{$promotion}'
                    WHEN bookings.traffic_source = 'campaign' THEN '{$unknown}'
                    WHEN bookings.traffic_source IN ({$recognized}) THEN bookings.traffic_source
                    WHEN bookings.traffic_source IS NULL THEN '{$direct}'
                    ELSE '{$unknown}'
                END
            )
            SQL;
    }

    /** @param array<int, array<string, mixed>> $sources
     * @return array<string, int|string>
     */
    private function acquisitionMetrics(array $sources): array
    {
        $rows = collect($sources);
        $promotion = $rows->where('source', AcquisitionSource::MarketplacePromotion->value);
        $google = $rows->whereIn('source', [
            AcquisitionSource::GoogleOrganic->value,
            AcquisitionSource::GoogleMaps->value,
        ]);
        $qrReferral = $rows->whereIn('source', [
            AcquisitionSource::QrCode->value,
            AcquisitionSource::Referral->value,
        ]);

        return [
            'promoted_bookings' => (int) $promotion->sum('bookings'),
            'promoted_revenue' => number_format((float) $promotion->sum('revenue'), 2, '.', ''),
            'google_bookings' => (int) $google->sum('bookings'),
            'google_revenue' => number_format((float) $google->sum('revenue'), 2, '.', ''),
            'qr_referral_bookings' => (int) $qrReferral->sum('bookings'),
            'qr_referral_revenue' => number_format((float) $qrReferral->sum('revenue'), 2, '.', ''),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function promotionPerformance(
        AnalyticsPeriod $period,
        ?Organization $organization,
        ?Venue $venue,
    ): array {
        $promotions = Promotion::query()
            ->when($organization, fn (Builder $query) => $query->where('organization_id', $organization->getKey()))
            ->when($venue, fn (Builder $query) => $query->where('venue_id', $venue->getKey()))
            ->with('venue:id,name')
            ->orderByDesc('created_at')
            ->limit($organization ? 100 : 20)
            ->get(['id', 'venue_id', 'title', 'campaign_token']);

        if ($promotions->isEmpty()) {
            return [];
        }

        $eventCounts = AnalyticsEvent::query()
            ->whereIn('promotion_id', $promotions->modelKeys())
            ->where('occurred_at', '>=', $period->utcStart)
            ->where('occurred_at', '<', $period->utcEnd)
            ->whereIn('event_type', [AnalyticsEventType::PromotionImpression, AnalyticsEventType::PromotionClick])
            ->selectRaw('promotion_id, event_type, COUNT(*) as aggregate')
            ->groupBy('promotion_id', 'event_type')
            ->get()
            ->groupBy('promotion_id');

        $bookingRows = $this->qualifiedBookings(Booking::query()
            ->whereIn('promotion_id', $promotions->modelKeys())
            ->where('source', BookingSource::Marketplace)
            ->where('created_at', '>=', $period->utcStart)
            ->where('created_at', '<', $period->utcEnd))
            ->selectRaw('promotion_id, COUNT(*) as bookings, SUM(total_amount) as revenue')
            ->groupBy('promotion_id')
            ->get()
            ->keyBy('promotion_id');

        return $promotions->map(function (Promotion $promotion) use ($eventCounts, $bookingRows): array {
            $events = $eventCounts->get($promotion->getKey(), collect())->keyBy(fn (AnalyticsEvent $event) => $event->event_type->value);
            $bookings = $bookingRows->get($promotion->getKey());

            return [
                'id' => $promotion->getKey(),
                'title' => $promotion->title,
                'campaign_token' => $promotion->campaign_token,
                'venue' => $promotion->venue?->name,
                'impressions' => (int) ($events->get(AnalyticsEventType::PromotionImpression->value)?->aggregate ?? 0),
                'clicks' => (int) ($events->get(AnalyticsEventType::PromotionClick->value)?->aggregate ?? 0),
                'bookings' => (int) ($bookings?->bookings ?? 0),
                'revenue' => number_format((float) ($bookings?->revenue ?? 0), 2, '.', ''),
            ];
        })->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function organizationPerformance(AnalyticsPeriod $period): array
    {
        $bookingRows = $this->qualifiedBookings($this->bookingQuery($period, null, null))
            ->selectRaw('organization_id, COUNT(*) as bookings, SUM(total_amount) as revenue')
            ->groupBy('organization_id')
            ->orderByDesc('bookings')
            ->limit(20)
            ->get()
            ->keyBy('organization_id');
        $viewRows = AnalyticsEvent::query()
            ->whereNotNull('organization_id')
            ->where('event_type', AnalyticsEventType::VenueProfileView)
            ->where('occurred_at', '>=', $period->utcStart)
            ->where('occurred_at', '<', $period->utcEnd)
            ->selectRaw('organization_id, COUNT(*) as profile_views')
            ->groupBy('organization_id')
            ->get()
            ->keyBy('organization_id');
        $ids = $bookingRows->keys()->merge($viewRows->keys())->unique()->values();

        return Organization::query()
            ->whereIn('id', $ids)
            ->get(['id', 'name'])
            ->map(function (Organization $organization) use ($bookingRows, $viewRows): array {
                $booking = $bookingRows->get($organization->getKey());

                return [
                    'name' => $organization->name,
                    'profile_views' => (int) ($viewRows->get($organization->getKey())?->profile_views ?? 0),
                    'bookings' => (int) ($booking?->bookings ?? 0),
                    'revenue' => number_format((float) ($booking?->revenue ?? 0), 2, '.', ''),
                ];
            })
            ->sortByDesc('bookings')
            ->values()
            ->take(20)
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function venuePerformance(AnalyticsPeriod $period): array
    {
        return collect([
            ...$this->marketplaceVenuePerformance($period),
            ...$this->directoryListingPerformance($period),
        ])
            ->sort(function (array $left, array $right): int {
                foreach (['unique_visitors', 'profile_views', 'availability_views', 'bookings'] as $metric) {
                    if ($left[$metric] !== $right[$metric]) {
                        return $right[$metric] <=> $left[$metric];
                    }
                }

                return strcasecmp($left['name'], $right['name']);
            })
            ->values()
            ->take(50)
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function marketplaceVenuePerformance(AnalyticsPeriod $period): array
    {
        $bookingRows = $this->qualifiedBookings($this->bookingQuery($period, null, null))
            ->selectRaw('venue_id, COUNT(*) as bookings, SUM(total_amount) as revenue')
            ->groupBy('venue_id')
            ->get()
            ->keyBy('venue_id');

        $eventRows = AnalyticsEvent::query()
            ->whereNotNull('venue_id')
            ->whereNull('venue_directory_listing_id')
            ->where('occurred_at', '>=', $period->utcStart)
            ->where('occurred_at', '<', $period->utcEnd)
            ->whereIn('event_type', [
                AnalyticsEventType::VenueImpression,
                AnalyticsEventType::VenueProfileView,
                AnalyticsEventType::AvailabilityView,
            ])
            ->selectRaw(
                'venue_id,
                SUM(CASE WHEN event_type = ? THEN 1 ELSE 0 END) as impressions,
                SUM(CASE WHEN event_type = ? THEN 1 ELSE 0 END) as profile_views,
                COUNT(DISTINCT CASE WHEN event_type = ? AND visitor_hash IS NOT NULL THEN visitor_hash END) as unique_visitors,
                SUM(CASE WHEN event_type = ? THEN 1 ELSE 0 END) as availability_views',
                [
                    AnalyticsEventType::VenueImpression->value,
                    AnalyticsEventType::VenueProfileView->value,
                    AnalyticsEventType::VenueProfileView->value,
                    AnalyticsEventType::AvailabilityView->value,
                ],
            )
            ->groupBy('venue_id')
            ->get()
            ->keyBy('venue_id');

        $ids = $bookingRows->keys()->merge($eventRows->keys())->unique()->values();

        if ($ids->isEmpty()) {
            return [];
        }

        $bookableVenueIds = Venue::query()
            ->marketplace()
            ->whereIn('id', $ids)
            ->pluck('id')
            ->flip();

        return Venue::query()
            ->with('organization:id,name')
            ->whereIn('id', $ids)
            ->get(['id', 'organization_id', 'name', 'slug', 'city', 'province', 'is_published', 'verified_at'])
            ->map(function (Venue $venue) use ($bookingRows, $eventRows, $bookableVenueIds): array {
                $event = $eventRows->get($venue->getKey());
                $booking = $bookingRows->get($venue->getKey());
                $isBookable = $bookableVenueIds->has($venue->getKey());

                return [
                    'key' => 'venue-'.$venue->getKey(),
                    'kind' => 'marketplace_venue',
                    'id' => $venue->getKey(),
                    'name' => $venue->name,
                    'slug' => $venue->slug,
                    'organization' => $venue->organization?->name,
                    'location' => collect([$venue->city, $venue->province])->filter()->implode(', '),
                    'booking_status' => $isBookable ? 'Bookable on FinACourt' : 'Not bookable yet',
                    'status_label' => $isBookable
                        ? 'Live marketplace venue'
                        : ($venue->is_published ? 'Published, waiting for review or courts' : 'Private setup'),
                    'public_url' => $isBookable && filled($venue->slug) ? "/venues/{$venue->slug}" : null,
                    'impressions' => (int) ($event?->impressions ?? 0),
                    'profile_views' => (int) ($event?->profile_views ?? 0),
                    'unique_visitors' => (int) ($event?->unique_visitors ?? 0),
                    'availability_views' => (int) ($event?->availability_views ?? 0),
                    'bookings' => (int) ($booking?->bookings ?? 0),
                    'revenue' => number_format((float) ($booking?->revenue ?? 0), 2, '.', ''),
                ];
            })
            ->values()
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function directoryListingPerformance(AnalyticsPeriod $period): array
    {
        $eventRows = AnalyticsEvent::query()
            ->whereNotNull('venue_directory_listing_id')
            ->where('event_type', AnalyticsEventType::VenueProfileView)
            ->where('occurred_at', '>=', $period->utcStart)
            ->where('occurred_at', '<', $period->utcEnd)
            ->selectRaw(
                'venue_directory_listing_id,
                COUNT(*) as profile_views,
                COUNT(DISTINCT CASE WHEN visitor_hash IS NOT NULL THEN visitor_hash END) as unique_visitors',
            )
            ->groupBy('venue_directory_listing_id')
            ->get()
            ->keyBy('venue_directory_listing_id');

        if ($eventRows->isEmpty()) {
            return [];
        }

        return VenueDirectoryListing::query()
            ->whereIn('id', $eventRows->keys())
            ->with('sports:id,name')
            ->get(['id', 'slug', 'status', 'name', 'city', 'province'])
            ->map(function (VenueDirectoryListing $listing) use ($eventRows): array {
                $event = $eventRows->get($listing->getKey());

                return [
                    'key' => 'directory-'.$listing->getKey(),
                    'kind' => 'directory_listing',
                    'id' => $listing->getKey(),
                    'name' => $listing->name,
                    'slug' => $listing->slug,
                    'organization' => 'Not joined yet',
                    'location' => collect([$listing->city, $listing->province])->filter()->implode(', '),
                    'booking_status' => 'Not bookable yet',
                    'status_label' => $listing->status->label(),
                    'public_url' => filled($listing->slug) ? "/directory/{$listing->slug}" : null,
                    'sports' => $listing->sports->pluck('name')->values()->all(),
                    'impressions' => 0,
                    'profile_views' => (int) ($event?->profile_views ?? 0),
                    'unique_visitors' => (int) ($event?->unique_visitors ?? 0),
                    'availability_views' => 0,
                    'bookings' => 0,
                    'revenue' => '0.00',
                ];
            })
            ->values()
            ->all();
    }
}
