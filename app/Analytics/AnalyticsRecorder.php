<?php

namespace App\Analytics;

use App\Enums\AcquisitionSource;
use App\Enums\AnalyticsEventType;
use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\DirectoryListingStatus;
use App\Marketplace\MarketplaceSearchResult;
use App\Models\AnalyticsEvent;
use App\Models\Booking;
use App\Models\CourtResource;
use App\Models\Promotion;
use App\Models\Venue;
use App\Models\VenueDirectoryListing;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use LogicException;

class AnalyticsRecorder
{
    public function __construct(private readonly TrafficAttribution $attribution) {}

    /** @param array<string, mixed> $filters */
    public function recordMarketplaceSearch(
        Request $request,
        array $filters,
        MarketplaceSearchResult $result,
        string $entryContext = 'discovery',
    ): void {
        $metadata = collect($filters)
            ->only(['city', 'sport', 'setting', 'max_price', 'date', 'start_time', 'duration_minutes'])
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->all();
        $metadata['result_count'] = $result->availableVenueCount;
        $metadata['matching_venue_count'] = $result->matchingVenueCount;
        $metadata['available_result_count'] = $result->availableVenueCount;
        $metadata['search_outcome'] = $result->outcome->value;
        $metadata['entry_context'] = $entryContext;
        $metadata['schema_version'] = 2;
        $startTime = filled($filters['start_time'] ?? null) ? (string) $filters['start_time'] : null;
        $duration = filled($filters['duration_minutes'] ?? null) ? (int) $filters['duration_minutes'] : null;
        $endTime = $startTime !== null && $duration !== null
            ? CarbonImmutable::createFromFormat('!H:i', $startTime, 'UTC')->addMinutes($duration)->format('H:i')
            : null;

        $this->record(
            $request,
            AnalyticsEventType::MarketplaceSearch,
            metadata: $metadata,
            scope: hash('sha256', json_encode($metadata, JSON_THROW_ON_ERROR)),
            dimensions: [
                'demand_city_slug' => $filters['city'] ?? null,
                'demand_sport_slug' => $filters['sport'] ?? null,
                'demand_setting' => isset($filters['setting'])
                    ? (is_object($filters['setting']) ? $filters['setting']->value : $filters['setting'])
                    : null,
                'requested_date' => $filters['date'] ?? null,
                'requested_start_time' => $startTime,
                'requested_end_time' => $endTime,
                'duration_minutes' => $duration,
                'maximum_hourly_rate' => filled($filters['max_price'] ?? null)
                    ? number_format((float) $filters['max_price'], 2, '.', '')
                    : null,
                'matching_venue_count' => $result->matchingVenueCount,
                'available_result_count' => $result->availableVenueCount,
                'search_outcome' => $result->outcome->value,
                'entry_context' => $entryContext,
                'is_demo' => false,
            ],
        );
    }

    /** @param Collection<int, Venue> $venues */
    public function recordVenueImpressions(Request $request, Collection $venues): void
    {
        $venues = $venues->unique('id');

        if ($venues->isEmpty()) {
            return;
        }

        $visitorHash = $this->visitorHash($request);
        $source = $this->attribution->current($request);
        $now = now('UTC');
        $dateBucket = $now->toDateString();
        $rows = $venues->map(fn (Venue $venue) => [
            'organization_id' => $venue->organization_id,
            'venue_id' => $venue->getKey(),
            'resource_id' => null,
            'promotion_id' => null,
            'booking_id' => null,
            'event_type' => AnalyticsEventType::VenueImpression->value,
            'visitor_hash' => $visitorHash,
            'traffic_source' => $source['source']->value,
            'source_detail' => $source['detail'],
            'dedupe_key' => hash('sha256', "{$visitorHash}|venue_impression|{$dateBucket}|venue:{$venue->getKey()}"),
            'metadata' => null,
            'occurred_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        AnalyticsEvent::query()->insertOrIgnore($rows);
    }

    public function recordVenueProfileView(Request $request, Venue $venue): void
    {
        $this->record(
            $request,
            AnalyticsEventType::VenueProfileView,
            venue: $venue,
            scope: "venue:{$venue->getKey()}",
        );
    }

    public function recordDirectoryProfileView(Request $request, VenueDirectoryListing $listing): void
    {
        $visitorHash = $this->visitorHash($request);
        $source = $this->attribution->current($request);
        $now = now('UTC');
        $claimedVenue = $listing->status === DirectoryListingStatus::Claimed
            ? $listing->claimedVenue
            : null;

        AnalyticsEvent::query()->insertOrIgnore([[
            'organization_id' => $claimedVenue?->organization_id,
            'venue_id' => $claimedVenue?->getKey(),
            'venue_directory_listing_id' => $listing->getKey(),
            'resource_id' => null,
            'promotion_id' => null,
            'booking_id' => null,
            // A directory profile view becomes an ordinary venue profile view
            // only after an approved claim assigns it to a real tenant venue.
            'event_type' => AnalyticsEventType::VenueProfileView->value,
            'visitor_hash' => $visitorHash,
            'traffic_source' => $source['source']->value,
            'source_detail' => $source['detail'],
            'dedupe_key' => hash('sha256', "{$visitorHash}|directory_profile_view|{$now->toDateString()}|listing:{$listing->getKey()}"),
            'metadata' => json_encode(['schema_version' => 1, 'listing_state' => $listing->status->value], JSON_THROW_ON_ERROR),
            'occurred_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]]);
    }

    public function recordAvailabilityView(
        Request $request,
        Venue $venue,
        CourtResource $resource,
        string $date,
    ): void {
        $this->record(
            $request,
            AnalyticsEventType::AvailabilityView,
            venue: $venue,
            resource: $resource,
            metadata: ['availability_date' => $date],
            scope: "resource:{$resource->getKey()}:date:{$date}",
        );
    }

    public function recordPromotionImpression(Request $request, Promotion $promotion): bool
    {
        return $this->record(
            $request,
            AnalyticsEventType::PromotionImpression,
            venue: $promotion->venue,
            promotion: $promotion,
            metadata: $this->promotionMetadata($request, $promotion),
            scope: "promotion:{$promotion->getKey()}",
        );
    }

    public function recordPromotionClick(Request $request, Promotion $promotion): bool
    {
        return $this->record(
            $request,
            AnalyticsEventType::PromotionClick,
            venue: $promotion->venue,
            promotion: $promotion,
            metadata: $this->promotionMetadata($request, $promotion),
            scope: "promotion:{$promotion->getKey()}",
        );
    }

    public function recordBookingStart(Request $request, Booking $booking): void
    {
        if ($booking->source !== BookingSource::Marketplace) {
            return;
        }

        $this->record(
            $request,
            AnalyticsEventType::BookingStart,
            venue: $booking->venue,
            resource: $booking->resource,
            promotion: $booking->promotion,
            booking: $booking,
            scope: "booking:{$booking->getKey()}",
        );
    }

    public function recordBookingCompleted(Booking $booking): void
    {
        if ($booking->source !== BookingSource::Marketplace || $booking->status !== BookingStatus::Confirmed) {
            return;
        }

        $this->recordLifecycle(
            AnalyticsEventType::CompletedBooking,
            $booking,
            "booking:{$booking->getKey()}",
        );
    }

    /** @return array<string, string> */
    private function promotionMetadata(Request $request, Promotion $promotion): array
    {
        $metadata = [
            'campaign_token' => $promotion->campaign_token,
            'campaign_goal' => $promotion->goal->value,
        ];
        $slotToken = $request->query('slot');

        if (is_string($slotToken) && $promotion->slots()->where('slot_token', $slotToken)->exists()) {
            $metadata['placement_token'] = $slotToken;
        }

        return $metadata;
    }

    /** @param array<string, mixed> $metadata
     * @param  array<string, mixed>  $dimensions
     */
    private function record(
        Request $request,
        AnalyticsEventType $type,
        ?Venue $venue = null,
        ?CourtResource $resource = null,
        ?Promotion $promotion = null,
        ?Booking $booking = null,
        array $metadata = [],
        string $scope = '',
        array $dimensions = [],
    ): bool {
        $this->assertConsistent($venue, $resource, $promotion, $booking);
        $visitorHash = $this->visitorHash($request);
        // Rendering a promotion is an impression, not an acquisition touch.
        // Only an explicit, server-resolved promotion click may claim the
        // visitor's latest touch; booking selection is validated separately.
        $source = $this->attribution->current(
            $request,
            $type === AnalyticsEventType::PromotionClick ? $promotion : null,
        );
        $dateBucket = now('UTC')->toDateString();

        return AnalyticsEvent::query()->insertOrIgnore([[
            ...$this->associations($venue, $resource, $promotion, $booking),
            ...$dimensions,
            'event_type' => $type->value,
            'visitor_hash' => $visitorHash,
            'traffic_source' => $source['source']->value,
            'source_detail' => $source['detail'],
            'dedupe_key' => hash('sha256', "{$visitorHash}|{$type->value}|{$dateBucket}|{$scope}"),
            'metadata' => $metadata === [] ? null : json_encode($metadata, JSON_THROW_ON_ERROR),
            'occurred_at' => now('UTC'),
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]]) === 1;
    }

    private function recordLifecycle(AnalyticsEventType $type, Booking $booking, string $scope): void
    {
        $booking->loadMissing(['venue', 'resource', 'promotion']);
        $this->assertConsistent($booking->venue, $booking->resource, $booking->promotion, $booking);

        AnalyticsEvent::query()->insertOrIgnore([[
            ...$this->associations($booking->venue, $booking->resource, $booking->promotion, $booking),
            'event_type' => $type->value,
            'visitor_hash' => null,
            'traffic_source' => $booking->traffic_source ?: AcquisitionSource::Direct->value,
            'source_detail' => $booking->traffic_source_detail,
            'dedupe_key' => hash('sha256', "lifecycle|{$type->value}|{$scope}"),
            'metadata' => null,
            'occurred_at' => now('UTC'),
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]]);
    }

    /** @return array<string, int|null> */
    private function associations(
        ?Venue $venue,
        ?CourtResource $resource,
        ?Promotion $promotion,
        ?Booking $booking,
    ): array {
        return [
            'organization_id' => $booking?->organization_id ?? $promotion?->organization_id ?? $venue?->organization_id,
            'venue_id' => $booking?->venue_id ?? $promotion?->venue_id ?? $venue?->getKey(),
            'resource_id' => $booking?->resource_id ?? $promotion?->resource_id ?? $resource?->getKey(),
            'promotion_id' => $booking?->promotion_id ?? $promotion?->getKey(),
            'booking_id' => $booking?->getKey(),
        ];
    }

    private function assertConsistent(
        ?Venue $venue,
        ?CourtResource $resource,
        ?Promotion $promotion,
        ?Booking $booking,
    ): void {
        $organizationId = $booking?->organization_id ?? $promotion?->organization_id ?? $venue?->organization_id;
        $venueId = $booking?->venue_id ?? $promotion?->venue_id ?? $venue?->getKey();

        if (($venue !== null && $organizationId !== $venue->organization_id)
            || ($resource !== null && $resource->venue_id !== $venueId)
            || ($promotion !== null && ($promotion->organization_id !== $organizationId || $promotion->venue_id !== $venueId))
            || ($booking !== null && ($booking->organization_id !== $organizationId || $booking->venue_id !== $venueId))) {
            throw new LogicException('Analytics associations must belong to the same organization and venue.');
        }
    }

    private function visitorHash(Request $request): string
    {
        $token = $request->session()->get('analytics.visitor_token');

        if (! is_string($token) || strlen($token) < 32) {
            $token = Str::random(64);
            $request->session()->put('analytics.visitor_token', $token);
        }

        return hash_hmac('sha256', $token, (string) config('app.key'));
    }
}
