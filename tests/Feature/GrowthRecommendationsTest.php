<?php

namespace Tests\Feature;

use App\Enums\AcquisitionSource;
use App\Enums\AnalyticsEventType;
use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\DemandSearchOutcome;
use App\Enums\GrowthRecommendationStateStatus;
use App\Enums\GrowthRecommendationType;
use App\Enums\PaymentStatus;
use App\Growth\GrowthRecommendationEngine;
use App\Models\AnalyticsEvent;
use App\Models\Booking;
use App\Models\CourtResource;
use App\Models\Membership;
use App\Models\OperatingHour;
use App\Models\Organization;
use App\Models\Promotion;
use App\Models\Sport;
use App\Models\User;
use App\Models\Venue;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class GrowthRecommendationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-24 04:00:00', 'UTC'));
        config([
            'growth.empty_inventory.minimum_slots' => 1000,
            'growth.demand.minimum_searches' => 1000,
            'growth.demand.minimum_unfulfilled_searches' => 1000,
            'growth.inactive_customers.minimum_customers' => 1000,
            'growth.successful_campaign.minimum_bookings' => 1000,
            'growth.low_conversion.minimum_profile_views' => 1000,
            'growth.channel_comparison.minimum_visitors_per_channel' => 1000,
        ]);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_empty_inventory_rule_uses_real_upcoming_slots_and_links_to_a_tenant_validated_action(): void
    {
        config(['growth.empty_inventory.minimum_slots' => 1]);
        [$organization, $venue, $resource] = $this->inventory('empty-inventory');

        $recommendation = $this->recommendation($organization, GrowthRecommendationType::EmptyInventory);

        $this->assertSame($venue->getKey(), $recommendation->venueId);
        $this->assertGreaterThanOrEqual(1, $recommendation->evidence['empty_slot_count']);
        $this->assertStringContainsString('/owner/promotions/create', $recommendation->actionUrl);
        $this->assertStringContainsString('resource='.$resource->getKey(), $recommendation->actionUrl);
        $headlineCount = $recommendation->evidence['last_minute_slot_count'] > 0
            ? $recommendation->evidence['last_minute_slot_count']
            : $recommendation->evidence['empty_slot_count'];
        $this->assertStringContainsString((string) $headlineCount, $recommendation->title);
        $this->assertSame('Create a promotion', $recommendation->actionLabel);
    }

    public function test_demand_rules_require_privacy_thresholded_real_searches_and_available_inventory(): void
    {
        config([
            'growth.demand.minimum_searches' => 3,
            'growth.demand.minimum_unfulfilled_searches' => 3,
            'growth.demand.minimum_available_slots' => 1,
        ]);
        [$organization] = $this->inventory('demand-rules');

        foreach ([
            DemandSearchOutcome::NoResults,
            DemandSearchOutcome::VenuesFoundNoAvailability,
            DemandSearchOutcome::VenuesFoundNoAvailability,
        ] as $index => $outcome) {
            $this->demandEvent("demand-{$index}", $outcome);
        }

        $report = app(GrowthRecommendationEngine::class)->report($organization, limit: 20);
        $demand = $report->active->firstWhere('type', GrowthRecommendationType::DemandWithInventory);
        $unfulfilled = $report->active->firstWhere('type', GrowthRecommendationType::UnfulfilledDemand);

        $this->assertNotNull($demand);
        $this->assertNotNull($unfulfilled);
        $this->assertSame(3, $demand->evidence['searches']);
        $this->assertSame(3, $demand->evidence['unique_searchers']);
        $this->assertSame(3, $unfulfilled->evidence['unfulfilled_searches']);
        $this->assertArrayNotHasKey('visitor_hash', $demand->evidence);
        $this->assertArrayNotHasKey('player_id', $unfulfilled->evidence);
        $this->assertStringContainsString('3', $unfulfilled->explanation);
    }

    public function test_inactive_customer_rule_is_aggregated_and_tenant_scoped(): void
    {
        config(['growth.inactive_customers.minimum_customers' => 1]);
        [$organization, $venue, $resource] = $this->inventory('inactive-customers');
        [$otherOrganization, $otherVenue, $otherResource] = $this->inventory('inactive-other');
        $this->completedBooking($organization, $venue, $resource, User::factory()->create(), now()->subDays(40));
        $this->completedBooking($otherOrganization, $otherVenue, $otherResource, User::factory()->create(), now()->subDays(45));

        $recommendation = $this->recommendation($organization, GrowthRecommendationType::InactiveCustomers);

        $this->assertSame(1, $recommendation->evidence['inactive_customer_count']);
        $this->assertStringContainsString('/owner/reactivation/create', $recommendation->actionUrl);
        $this->assertStringContainsString('segment=inactive_30', $recommendation->actionUrl);
        $this->assertArrayNotHasKey('customer_name', $recommendation->evidence);
    }

    public function test_successful_campaign_rule_uses_only_qualified_booking_value(): void
    {
        config(['growth.successful_campaign.minimum_bookings' => 2]);
        [$organization, $venue, $resource] = $this->inventory('successful-campaign');
        $promotion = Promotion::factory()->for($venue)->create();

        foreach ([PaymentStatus::Paid, PaymentStatus::Pending, PaymentStatus::Refunded] as $paymentStatus) {
            Booking::factory()->for($resource, 'resource')->create([
                'organization_id' => $organization->getKey(),
                'venue_id' => $venue->getKey(),
                'promotion_id' => $promotion->getKey(),
                'source' => BookingSource::Marketplace,
                'status' => BookingStatus::Confirmed,
                'payment_status' => $paymentStatus,
                'total_amount' => '500.00',
                'created_at' => now()->subDay(),
            ]);
        }

        $recommendation = $this->recommendation($organization, GrowthRecommendationType::RepeatSuccessfulCampaign);

        $this->assertSame(2, $recommendation->evidence['qualified_bookings']);
        $this->assertSame(1000.0, $recommendation->evidence['qualified_booking_value']);
        $this->assertSame(route('owner.promotions.show', $promotion), $recommendation->actionUrl);
    }

    public function test_high_traffic_low_conversion_rule_uses_actual_venue_events_and_booking_state(): void
    {
        config([
            'growth.low_conversion.minimum_profile_views' => 3,
            'growth.low_conversion.minimum_unique_visitors' => 3,
            'growth.low_conversion.maximum_booking_rate_percent' => 5,
        ]);
        [$organization, $venue] = $this->inventory('low-conversion');

        foreach (range(1, 3) as $index) {
            $this->profileView($venue, AcquisitionSource::MarketplaceOrganic, "view-{$index}");
        }

        $recommendation = $this->recommendation($organization, GrowthRecommendationType::LowBookingConversion);

        $this->assertSame(3, $recommendation->evidence['profile_views']);
        $this->assertSame(3, $recommendation->evidence['unique_visitors']);
        $this->assertSame(0, $recommendation->evidence['qualified_bookings']);
        $this->assertSame(0.0, $recommendation->evidence['conversion_rate_percent']);
        $this->assertStringContainsString('/owner/visibility', $recommendation->actionUrl);
    }

    public function test_channel_rule_requires_two_sufficient_channels_and_immutable_booking_attribution(): void
    {
        config([
            'growth.channel_comparison.minimum_visitors_per_channel' => 2,
            'growth.channel_comparison.minimum_total_bookings' => 2,
            'growth.channel_comparison.minimum_gap_percentage_points' => 5,
        ]);
        [$organization, $venue, $resource] = $this->inventory('channel-gap');

        foreach (range(1, 2) as $index) {
            $this->profileView($venue, AcquisitionSource::GoogleMaps, "google-{$index}");
            $this->profileView($venue, AcquisitionSource::Direct, "direct-{$index}");
            $booking = Booking::factory()->for($resource, 'resource')->create([
                'organization_id' => $organization->getKey(),
                'venue_id' => $venue->getKey(),
                'source' => BookingSource::Marketplace,
                'status' => BookingStatus::Confirmed,
                'payment_status' => PaymentStatus::Paid,
                'created_at' => now()->subDay(),
            ]);
            $booking->attribution()->create($this->attribution($organization, $venue, AcquisitionSource::GoogleMaps));
        }

        $recommendation = $this->recommendation($organization, GrowthRecommendationType::ChannelConversionGap);

        $this->assertSame('google_maps', $recommendation->evidence['stronger_source']);
        $this->assertSame(2, $recommendation->evidence['stronger_qualified_bookings']);
        $this->assertSame('direct', $recommendation->evidence['comparison_source']);
        $this->assertSame(0, $recommendation->evidence['comparison_qualified_bookings']);
        $this->assertStringContainsString('helpful sign', $recommendation->explanation);
    }

    public function test_owner_can_snooze_dismiss_resolve_and_restore_only_current_tenant_recommendations(): void
    {
        config(['growth.empty_inventory.minimum_slots' => 1]);
        [$organization, , , $owner] = $this->inventory('suppression', true);
        [, , , $otherOwner] = $this->inventory('suppression-other', true);
        $engine = app(GrowthRecommendationEngine::class);
        $recommendation = $engine->report($organization, limit: 20)->active->firstOrFail();

        $this->actingAs($otherOwner)->post(route('owner.growth.state.store', $recommendation->key), [
            'status' => GrowthRecommendationStateStatus::Dismissed->value,
        ])->assertNotFound();

        $this->actingAs($owner)->post(route('owner.growth.state.store', $recommendation->key), [
            'status' => GrowthRecommendationStateStatus::Snoozed->value,
            'snooze_days' => 7,
        ])->assertRedirect();
        $this->assertSame(1, $engine->report($organization, limit: 20)->suppressed->count());

        CarbonImmutable::setTestNow(CarbonImmutable::now('UTC')->addDays(8));
        $this->assertTrue($engine->report($organization, limit: 20)->active->contains('key', $recommendation->key));

        $this->actingAs($owner)->post(route('owner.growth.state.store', $recommendation->key), [
            'status' => GrowthRecommendationStateStatus::Dismissed->value,
        ])->assertRedirect();
        $this->assertDatabaseHas('growth_recommendation_states', [
            'organization_id' => $organization->getKey(),
            'recommendation_key' => $recommendation->key,
            'status' => GrowthRecommendationStateStatus::Dismissed->value,
        ]);

        $this->actingAs($owner)->post(route('owner.growth.state.store', $recommendation->key), [
            'status' => GrowthRecommendationStateStatus::Resolved->value,
        ])->assertRedirect();
        $this->actingAs($owner)->delete(route('owner.growth.state.destroy', $recommendation->key))->assertRedirect();
        $this->assertDatabaseMissing('growth_recommendation_states', [
            'organization_id' => $organization->getKey(),
            'recommendation_key' => $recommendation->key,
        ]);
    }

    public function test_recommendations_have_explicit_staleness_and_safe_insufficient_data_behavior(): void
    {
        $organization = Organization::factory()->create();
        $owner = User::factory()->create();
        Membership::factory()->owner()->for($owner)->for($organization)->create();
        $report = app(GrowthRecommendationEngine::class)->report($organization);

        $this->assertTrue($report->active->isEmpty());
        $this->assertTrue($report->suppressed->isEmpty());
        $this->actingAs($owner)->get(route('owner.growth.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Owner/Growth/Index')
                ->has('report.active', 0)
                ->where('report.has_sufficient_data', false));

        config(['growth.empty_inventory.minimum_slots' => 1]);
        [$inventoryOrganization] = $this->inventory('staleness');
        $recommendation = app(GrowthRecommendationEngine::class)
            ->report($inventoryOrganization, limit: 20)
            ->active
            ->firstOrFail();
        $this->assertFalse($recommendation->isStale($recommendation->calculatedAt));
        $this->assertTrue($recommendation->isStale($recommendation->expiresAt));
    }

    public function test_platform_admin_can_debug_one_organization_without_exposing_another_tenant(): void
    {
        config(['growth.empty_inventory.minimum_slots' => 1]);
        [$organizationA] = $this->inventory('platform-growth-a');
        [$organizationB] = $this->inventory('platform-growth-b');
        $platform = User::factory()->platformAdmin()->create();
        $regular = User::factory()->create();

        $this->actingAs($regular)->get(route('platform.growth.index', ['organization' => $organizationA]))
            ->assertForbidden();
        $this->actingAs($platform)->get(route('platform.growth.index', ['organization' => $organizationA]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Platform/Growth/Index')
                ->where('selectedOrganization.id', $organizationA->getKey())
                ->has('report.active', fn (Assert $recommendations) => $recommendations
                    ->each(fn (Assert $recommendation) => $recommendation
                        ->where('organization_id', $organizationA->getKey())
                        ->whereNot('organization_id', $organizationB->getKey())
                        ->etc())));
    }

    public function test_recommendation_query_count_is_bounded_for_a_multi_venue_owner(): void
    {
        config(['growth.empty_inventory.minimum_slots' => 1]);
        [$organization] = $this->inventory('query-budget-a');
        $this->inventoryForOrganization($organization, 'query-budget-b');
        DB::flushQueryLog();
        DB::enableQueryLog();

        app(GrowthRecommendationEngine::class)->report($organization, limit: 20);

        $this->assertLessThanOrEqual(30, count(DB::getQueryLog()));
        DB::disableQueryLog();
    }

    private function recommendation(Organization $organization, GrowthRecommendationType $type)
    {
        return app(GrowthRecommendationEngine::class)
            ->report($organization, limit: 20)
            ->active
            ->firstWhere('type', $type)
            ?? $this->fail("Expected recommendation type {$type->value}.");
    }

    /** @return array{Organization, Venue, CourtResource, ?User, Sport} */
    private function inventory(string $slug, bool $withOwner = false): array
    {
        $organization = Organization::factory()->create(['timezone' => 'Asia/Manila']);
        [$venue, $resource, $sport] = $this->inventoryForOrganization($organization, $slug);
        $owner = null;

        if ($withOwner) {
            $owner = User::factory()->create();
            Membership::factory()->owner()->for($owner)->for($organization)->create();
        }

        return [$organization, $venue, $resource, $owner, $sport];
    }

    /** @return array{Venue, CourtResource, Sport} */
    private function inventoryForOrganization(Organization $organization, string $slug): array
    {
        $sport = Sport::query()->firstOrCreate(
            ['slug' => 'badminton'],
            ['name' => 'Badminton', 'is_active' => true],
        );
        $venue = Venue::factory()->for($organization)->published()->create([
            'name' => str($slug)->headline()->toString(),
            'slug' => $slug,
            'city' => 'Makati',
            'city_slug' => 'makati',
            'province' => 'Metro Manila',
        ]);
        $resource = CourtResource::factory()->for($venue)->for($sport)->create([
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

        return [$venue, $resource, $sport];
    }

    private function demandEvent(string $visitor, DemandSearchOutcome $outcome): AnalyticsEvent
    {
        return AnalyticsEvent::factory()->marketplaceSearch()->create([
            'visitor_hash' => hash('sha256', $visitor),
            'demand_city_slug' => 'makati',
            'demand_sport_slug' => 'badminton',
            'search_outcome' => $outcome,
            'matching_venue_count' => $outcome === DemandSearchOutcome::NoResults ? 0 : 1,
            'available_result_count' => $outcome === DemandSearchOutcome::ResultsAvailable ? 1 : 0,
            'occurred_at' => now('UTC')->subDay(),
        ]);
    }

    private function profileView(Venue $venue, AcquisitionSource $source, string $visitor): AnalyticsEvent
    {
        return AnalyticsEvent::factory()->for($venue)->create([
            'organization_id' => $venue->organization_id,
            'event_type' => AnalyticsEventType::VenueProfileView,
            'visitor_hash' => hash('sha256', $visitor),
            'traffic_source' => $source->value,
            'is_demo' => false,
            'occurred_at' => now('UTC')->subDay(),
        ]);
    }

    private function completedBooking(
        Organization $organization,
        Venue $venue,
        CourtResource $resource,
        User $player,
        CarbonInterface $endedAt,
    ): Booking {
        return Booking::factory()->for($resource, 'resource')->for($player, 'player')->create([
            'organization_id' => $organization->getKey(),
            'venue_id' => $venue->getKey(),
            'status' => BookingStatus::Confirmed,
            'source' => BookingSource::Marketplace,
            'payment_status' => PaymentStatus::Paid,
            'start_at' => $endedAt->subHour(),
            'end_at' => $endedAt,
        ]);
    }

    /** @return array<string, mixed> */
    private function attribution(
        Organization $organization,
        Venue $venue,
        AcquisitionSource $source,
    ): array {
        return [
            'organization_id' => $organization->getKey(),
            'venue_id' => $venue->getKey(),
            'first_source' => $source,
            'first_seen_at' => now('UTC')->subDay(),
            'last_source' => $source,
            'last_seen_at' => now('UTC')->subDay(),
            'attributed_source' => $source,
            'attributed_at' => now('UTC')->subDay(),
            'rule_version' => config('attribution.rule_version'),
        ];
    }
}
