<?php

namespace App\Models;

use App\Enums\AnalyticsEventType;
use App\Enums\DemandSearchOutcome;
use Database\Factories\AnalyticsEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'organization_id',
    'venue_id',
    'venue_directory_listing_id',
    'resource_id',
    'promotion_id',
    'booking_id',
    'event_type',
    'demand_city_slug',
    'demand_sport_slug',
    'demand_setting',
    'requested_date',
    'requested_start_time',
    'requested_end_time',
    'duration_minutes',
    'maximum_hourly_rate',
    'matching_venue_count',
    'available_result_count',
    'search_outcome',
    'entry_context',
    'is_demo',
    'visitor_hash',
    'traffic_source',
    'source_detail',
    'dedupe_key',
    'metadata',
    'occurred_at',
])]
class AnalyticsEvent extends Model
{
    /** @use HasFactory<AnalyticsEventFactory> */
    use HasFactory;

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<Venue, $this> */
    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    /** @return BelongsTo<VenueDirectoryListing, $this> */
    public function directoryListing(): BelongsTo
    {
        return $this->belongsTo(VenueDirectoryListing::class, 'venue_directory_listing_id');
    }

    /** @return BelongsTo<CourtResource, $this> */
    public function resource(): BelongsTo
    {
        return $this->belongsTo(CourtResource::class, 'resource_id');
    }

    /** @return BelongsTo<Promotion, $this> */
    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    /** @return BelongsTo<Booking, $this> */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    protected function casts(): array
    {
        return [
            'event_type' => AnalyticsEventType::class,
            'search_outcome' => DemandSearchOutcome::class,
            'requested_date' => 'immutable_date',
            'duration_minutes' => 'integer',
            'maximum_hourly_rate' => 'decimal:2',
            'matching_venue_count' => 'integer',
            'available_result_count' => 'integer',
            'is_demo' => 'boolean',
            'metadata' => 'array',
            'occurred_at' => 'immutable_datetime',
        ];
    }
}
