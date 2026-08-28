<?php

namespace Tests\Feature;

use App\Enums\AnalyticsEventType;
use App\Enums\BookingStatus;
use App\Enums\DemandSearchOutcome;
use App\Models\AnalyticsEvent;
use App\Models\Booking;
use App\Models\CourtResource;
use App\Models\Membership;
use App\Models\OperatingHour;
use App\Models\Organization;
use App\Models\Sport;
use App\Models\User;
use App\Models\Venue;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DemandIntelligenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_anonymous_marketplace_search_records_normalized_demand_dimensions(): void
    {
        [, $venue] = $this->inventory('makati-demand', 'Makati', 'makati', 'Badminton', 'badminton');
        $date = CarbonImmutable::now('Asia/Manila')->addDays(3)->toDateString();

        $this->withHeader('referer', 'https://search.example/courts')
            ->get(route('marketplace.courts.index', [
                'city' => 'makati',
                'sport' => 'badminton',
                'setting' => 'indoor',
                'max_price' => 600,
                'date' => $date,
                'start_time' => '18:00',
                'duration_minutes' => 60,
                'email' => 'must-not-be-recorded@example.com',
            ]))
            ->assertOk()
            ->assertSee($venue->name);

        $event = AnalyticsEvent::query()
            ->where('event_type', AnalyticsEventType::MarketplaceSearch)
            ->sole();

        $this->assertSame('makati', $event->demand_city_slug);
        $this->assertSame('badminton', $event->demand_sport_slug);
        $this->assertSame('indoor', $event->demand_setting);
        $this->assertSame($date, $event->requested_date->toDateString());
        $this->assertStringStartsWith('18:00', $event->requested_start_time);
        $this->assertStringStartsWith('19:00', $event->requested_end_time);
        $this->assertSame(60, $event->duration_minutes);
        $this->assertSame('600.00', $event->maximum_hourly_rate);
        $this->assertSame(1, $event->matching_venue_count);
        $this->assertSame(1, $event->available_result_count);
        $this->assertSame(DemandSearchOutcome::ResultsAvailable, $event->search_outcome);
        $this->assertSame('discovery', $event->entry_context);
        $this->assertSame('referral', $event->traffic_source);
        $this->assertNotNull($event->visitor_hash);
        $this->assertNull($event->organization_id);
        $this->assertArrayNotHasKey('email', $event->metadata);
    }

    public function test_authenticated_search_remains_anonymous_in_demand_storage(): void
    {
        $this->inventory('player-demand', 'Makati', 'makati', 'Badminton', 'badminton');
        $player = User::factory()->create();

        $this->actingAs($player)
            ->get(route('marketplace.courts.index', ['city' => 'makati']))
            ->assertOk();

        $event = AnalyticsEvent::query()
            ->where('event_type', AnalyticsEventType::MarketplaceSearch)
            ->sole();

        $this->assertNotNull($event->visitor_hash);
        $this->assertNull($event->organization_id);
        $this->assertNull($event->venue_id);
        $this->assertArrayNotHasKey('user_id', $event->getAttributes());
        $this->assertArrayNotHasKey('player_user_id', $event->getAttributes());
        $this->assertArrayNotHasKey('email', $event->metadata);
    }

    public function test_search_with_no_matching_supply_is_classified_as_no_results(): void
    {
        $this->get(route('marketplace.courts.index', [
            'city' => 'unserved-city',
            'sport' => 'pickleball',
        ]))->assertOk()->assertSee('0 venues found');

        $event = AnalyticsEvent::query()
            ->where('event_type', AnalyticsEventType::MarketplaceSearch)
            ->sole();

        $this->assertSame(0, $event->matching_venue_count);
        $this->assertSame(0, $event->available_result_count);
        $this->assertSame(DemandSearchOutcome::NoResults, $event->search_outcome);
    }

    public function test_matching_venue_without_a_bookable_time_is_classified_separately(): void
    {
        [, $venue, $resource] = $this->inventory(
            'no-availability-demand',
            'Makati',
            'makati',
            'Badminton',
            'badminton',
        );
        $date = CarbonImmutable::now('Asia/Manila')->addDays(5);
        $start = $date->setTime(18, 0);
        Booking::factory()->for($resource, 'resource')->create([
            'status' => BookingStatus::Confirmed,
            'start_at' => $start->utc(),
            'end_at' => $start->addHour()->utc(),
        ]);

        $this->get(route('marketplace.courts.index', [
            'city' => 'makati',
            'sport' => 'badminton',
            'date' => $date->toDateString(),
            'start_time' => '18:00',
            'duration_minutes' => 60,
        ]))
            ->assertOk()
            ->assertSee('0 venues found')
            ->assertDontSee($venue->name);

        $event = AnalyticsEvent::query()
            ->where('event_type', AnalyticsEventType::MarketplaceSearch)
            ->sole();

        $this->assertSame(1, $event->matching_venue_count);
        $this->assertSame(0, $event->available_result_count);
        $this->assertSame(DemandSearchOutcome::VenuesFoundNoAvailability, $event->search_outcome);
    }

    public function test_owner_demand_is_geographically_scoped_aggregated_and_tenant_safe(): void
    {
        [$organizationA, , , $ownerA] = $this->inventory(
            'owner-demand-a',
            'Makati',
            'makati',
            'Badminton',
            'badminton',
            true,
        );
        [, $venueB] = $this->inventory(
            'owner-demand-b',
            'Cebu City',
            'cebu-city',
            'Pickleball',
            'pickleball',
            true,
        );
        $outcomes = [
            DemandSearchOutcome::ResultsAvailable,
            DemandSearchOutcome::NoResults,
            DemandSearchOutcome::VenuesFoundNoAvailability,
        ];

        foreach ($outcomes as $index => $outcome) {
            $this->demandEvent("makati-owner-{$index}", 'makati', 'badminton', $outcome, '18:00');
        }

        foreach (range(1, 4) as $index) {
            $this->demandEvent("cebu-owner-{$index}", 'cebu-city', 'pickleball', DemandSearchOutcome::NoResults, '09:00');
        }

        $this->actingAs($ownerA)->get(route('owner.analytics'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Owner/Analytics/Index')
                ->where('demand.available', true)
                ->where('demand.privacy.suppressed', false)
                ->where('demand.metrics.searches', 3)
                ->where('demand.metrics.unique_searchers', 3)
                ->where('demand.metrics.no_results', 1)
                ->where('demand.metrics.no_availability', 1)
                ->where('demand.areas.0.label', 'Makati')
                ->where('demand.sports.0.label', 'Badminton')
                ->missing('demand.visitor_hash')
                ->missing('demand.sports.0.visitor_hash')
                ->missing('demand.areas.1'));

        $this->actingAs($ownerA)->get(route('owner.analytics', ['venue' => $venueB->getKey()]))
            ->assertNotFound();
        $this->assertSame($organizationA->getKey(), $ownerA->memberships()->value('organization_id'));
    }

    public function test_owner_demand_is_suppressed_below_the_distinct_session_threshold(): void
    {
        [, , , $owner] = $this->inventory(
            'owner-private-demand',
            'Makati',
            'makati',
            'Badminton',
            'badminton',
            true,
        );
        $this->demandEvent('private-a', 'makati', 'badminton', DemandSearchOutcome::NoResults, '18:00');
        $this->demandEvent('private-b', 'makati', 'badminton', DemandSearchOutcome::NoResults, '18:00');

        $this->actingAs($owner)->get(route('owner.analytics'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('demand.privacy.minimum_unique_searchers', 3)
                ->where('demand.privacy.suppressed', true)
                ->where('demand.metrics.searches', 0)
                ->has('demand.sports', 0)
                ->has('demand.time_buckets', 0));
    }

    public function test_platform_demand_aggregates_sport_area_time_and_outcomes(): void
    {
        $this->inventory('platform-makati', 'Makati', 'makati', 'Badminton', 'badminton');
        $this->inventory('platform-cebu', 'Cebu City', 'cebu-city', 'Pickleball', 'pickleball');
        $platform = User::factory()->platformAdmin()->create();

        foreach ([
            ['a', 'makati', 'badminton', DemandSearchOutcome::ResultsAvailable, '18:00'],
            ['b', 'makati', 'badminton', DemandSearchOutcome::ResultsAvailable, '19:00'],
            ['c', 'makati', 'badminton', DemandSearchOutcome::NoResults, '18:00'],
            ['d', 'makati', 'badminton', DemandSearchOutcome::VenuesFoundNoAvailability, '20:00'],
            ['e', 'cebu-city', 'pickleball', DemandSearchOutcome::NoResults, '09:00'],
        ] as [$visitor, $city, $sport, $outcome, $time]) {
            $this->demandEvent($visitor, $city, $sport, $outcome, $time);
        }
        $this->demandEvent('demo-excluded', 'makati', 'badminton', DemandSearchOutcome::NoResults, '18:00', true);
        $this->demandEvent('outside-period', 'makati', 'badminton', DemandSearchOutcome::NoResults, '18:00')
            ->update(['occurred_at' => now('UTC')->subDays(40)]);

        $this->actingAs($platform)->get(route('platform.analytics'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Platform/Analytics/Index')
                ->where('acquisition.metrics.searches', 5)
                ->where('acquisition.metrics.zero_result_searches', 2)
                ->where('acquisition.metrics.no_availability_searches', 1)
                ->where('acquisition.metrics.unfulfilled_searches', 3)
                ->where('acquisition.demand.metrics.coverage_rate', 40)
                ->where('acquisition.demand.sports.0.label', 'Badminton')
                ->where('acquisition.demand.sports.0.searches', 4)
                ->where('acquisition.demand.areas.0.label', 'Makati')
                ->where('acquisition.demand.time_buckets.0.bucket', 'evening')
                ->where('acquisition.demand.time_buckets.0.searches', 4)
                ->where('acquisition.demand_segments.0.no_availability', 1)
                ->missing('acquisition.demand.visitor_hash'));
    }

    /** @return array{Organization, Venue, CourtResource, ?User} */
    private function inventory(
        string $slug,
        string $city,
        string $citySlug,
        string $sportName,
        string $sportSlug,
        bool $withOwner = false,
    ): array {
        $organization = Organization::factory()->create(['timezone' => 'Asia/Manila']);
        $venue = Venue::factory()->for($organization)->published()->create([
            'name' => str($slug)->headline()->toString(),
            'slug' => $slug,
            'city' => $city,
            'city_slug' => $citySlug,
            'province' => $citySlug === 'cebu-city' ? 'Cebu' : 'Metro Manila',
        ]);
        $sport = Sport::query()->firstOrCreate(
            ['slug' => $sportSlug],
            ['name' => $sportName, 'is_active' => true],
        );
        $resource = CourtResource::factory()->for($venue)->for($sport)->create([
            'setting' => 'indoor',
            'base_hourly_rate' => '500.00',
            'booking_increment_minutes' => 60,
        ]);

        foreach (range(0, 6) as $day) {
            OperatingHour::factory()->for($venue)->create([
                'day_of_week' => $day,
                'opens_at' => '08:00',
                'closes_at' => '22:00',
            ]);
        }

        $owner = null;

        if ($withOwner) {
            $owner = User::factory()->create();
            Membership::factory()->owner()->for($owner)->for($organization)->create();
        }

        return [$organization, $venue, $resource, $owner];
    }

    private function demandEvent(
        string $visitor,
        string $city,
        string $sport,
        DemandSearchOutcome $outcome,
        string $startTime,
        bool $isDemo = false,
    ): AnalyticsEvent {
        $date = CarbonImmutable::now('Asia/Manila')->addDays(7)->toDateString();
        $matchingVenueCount = $outcome === DemandSearchOutcome::NoResults ? 0 : 1;
        $availableResultCount = $outcome === DemandSearchOutcome::ResultsAvailable ? 1 : 0;

        return AnalyticsEvent::factory()->marketplaceSearch()->create([
            'visitor_hash' => hash('sha256', $visitor),
            'demand_city_slug' => $city,
            'demand_sport_slug' => $sport,
            'requested_date' => $date,
            'requested_start_time' => $startTime,
            'requested_end_time' => CarbonImmutable::createFromFormat('!H:i', $startTime, 'UTC')
                ->addHour()
                ->format('H:i'),
            'matching_venue_count' => $matchingVenueCount,
            'available_result_count' => $availableResultCount,
            'search_outcome' => $outcome,
            'is_demo' => $isDemo,
            'metadata' => [
                'city' => $city,
                'sport' => $sport,
                'date' => $date,
                'start_time' => $startTime,
                'duration_minutes' => 60,
                'matching_venue_count' => $matchingVenueCount,
                'available_result_count' => $availableResultCount,
                'search_outcome' => $outcome->value,
                'schema_version' => 2,
                'local_demo' => $isDemo,
            ],
        ]);
    }
}
