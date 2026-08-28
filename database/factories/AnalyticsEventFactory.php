<?php

namespace Database\Factories;

use App\Enums\AcquisitionSource;
use App\Enums\AnalyticsEventType;
use App\Enums\DemandSearchOutcome;
use App\Models\AnalyticsEvent;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<AnalyticsEvent> */
class AnalyticsEventFactory extends Factory
{
    public function definition(): array
    {
        return [
            'venue_id' => Venue::factory(),
            'event_type' => AnalyticsEventType::VenueProfileView,
            'visitor_hash' => hash('sha256', Str::random(40)),
            'traffic_source' => AcquisitionSource::Direct->value,
            'source_detail' => null,
            'dedupe_key' => hash('sha256', Str::uuid()->toString()),
            'metadata' => null,
            'occurred_at' => now(),
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (AnalyticsEvent $event): void {
            $event->organization_id ??= $event->venue?->organization_id;
        });
    }

    public function marketplaceSearch(): static
    {
        return $this->state(fn () => [
            'organization_id' => null,
            'venue_id' => null,
            'resource_id' => null,
            'promotion_id' => null,
            'booking_id' => null,
            'event_type' => AnalyticsEventType::MarketplaceSearch,
            'demand_city_slug' => 'makati',
            'demand_sport_slug' => 'badminton',
            'demand_setting' => null,
            'requested_date' => now()->addDay()->toDateString(),
            'requested_start_time' => '18:00',
            'requested_end_time' => '19:00',
            'duration_minutes' => 60,
            'maximum_hourly_rate' => null,
            'matching_venue_count' => 1,
            'available_result_count' => 1,
            'search_outcome' => DemandSearchOutcome::ResultsAvailable,
            'entry_context' => 'discovery',
            'is_demo' => false,
            'metadata' => [
                'city' => 'makati',
                'sport' => 'badminton',
                'date' => now()->addDay()->toDateString(),
                'start_time' => '18:00',
                'duration_minutes' => 60,
                'result_count' => 1,
                'search_outcome' => DemandSearchOutcome::ResultsAvailable->value,
                'schema_version' => 2,
            ],
        ]);
    }
}
