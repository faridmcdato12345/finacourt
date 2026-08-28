<?php

namespace Tests\Feature;

use App\Analytics\AnalyticsRecorder;
use App\Enums\AnalyticsEventType;
use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\DemandSearchOutcome;
use App\Enums\PaymentStatus;
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
use App\Models\VenueDirectoryListing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use LogicException;
use Tests\TestCase;

class AnalyticsAttributionTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_marketplace_records_deduplicated_privacy_conscious_events(): void
    {
        [, $venue, $resource] = $this->setupInventory('public-analytics');

        $this->withHeader('referer', 'https://search.example/results?q=private')
            ->get(route('marketplace.courts.index', [
                'city' => $venue->city_slug,
                'email' => 'private@example.com',
            ]))->assertOk();
        $this->withHeader('referer', 'https://search.example/results?q=private')
            ->get(route('marketplace.courts.index', ['city' => $venue->city_slug]))
            ->assertOk();
        $this->get(route('marketplace.venues.show', [
            'venueSlug' => $venue->slug,
            'resource' => $resource->getKey(),
            'date' => now('Asia/Manila')->addDays(3)->toDateString(),
        ]))->assertOk();

        $this->assertSame(1, AnalyticsEvent::query()->where('event_type', AnalyticsEventType::VenueImpression)->count());
        $this->assertSame(1, AnalyticsEvent::query()->where('event_type', AnalyticsEventType::VenueProfileView)->count());
        $this->assertSame(1, AnalyticsEvent::query()->where('event_type', AnalyticsEventType::AvailabilityView)->count());
        $event = AnalyticsEvent::query()->where('event_type', AnalyticsEventType::MarketplaceSearch)->firstOrFail();
        $this->assertSame('referral', $event->traffic_source);
        $this->assertSame('search.example', $event->source_detail);
        $this->assertArrayNotHasKey('email', $event->metadata);
        $this->assertNotNull($event->visitor_hash);
        $this->assertSame(64, strlen($event->visitor_hash));
    }

    public function test_event_recorder_rejects_cross_tenant_associations(): void
    {
        [, $venueA] = $this->setupInventory('event-tenant-a');
        [, , $resourceB] = $this->setupInventory('event-tenant-b');
        $request = request()->create('/', 'GET');
        $request->setLaravelSession(app('session')->driver());

        $this->expectException(LogicException::class);
        app(AnalyticsRecorder::class)->recordAvailabilityView(
            $request,
            $venueA,
            $resourceB,
            now()->addDay()->toDateString(),
        );
    }

    public function test_booking_funnel_persists_server_side_source_and_lifecycle_events(): void
    {
        [, $venue, $resource] = $this->setupInventory('booking-attribution');
        $player = User::factory()->create();
        $date = now('Asia/Manila')->addDays(7)->toDateString();

        $this->get(route('marketplace.venues.show', [
            'venueSlug' => $venue->slug,
            'resource' => $resource->getKey(),
            'date' => $date,
            'utm_source' => 'facebook',
            'utm_campaign' => 'weekend courts',
        ]))->assertOk();
        $this->actingAs($player)->post(route('player.bookings.store', $venue->slug), [
            'resource_id' => $resource->getKey(),
            'booking_date' => $date,
            'start_time' => '09:00',
            'duration_minutes' => 60,
            'customer_name' => 'Analytics Player',
            'terms' => '1',
            'traffic_source' => 'browser-tampering',
        ])->assertRedirect();

        $booking = Booking::query()->where('player_user_id', $player->getKey())->firstOrFail();
        $this->assertSame('facebook', $booking->traffic_source);
        $this->assertSame('weekend courts', $booking->traffic_source_detail);
        $this->assertSame('facebook', $booking->attribution->attributed_source->value);
        $this->assertSame('facebook', $booking->attribution->first_source->value);
        $this->assertSame('weekend courts', $booking->attribution->attributed_campaign);
        $this->assertDatabaseHas('analytics_events', [
            'booking_id' => $booking->getKey(),
            'event_type' => AnalyticsEventType::BookingStart->value,
        ]);

        $this->actingAs($player)->post(route('player.bookings.confirm', $booking->reference))
            ->assertRedirect();
        $this->assertDatabaseHas('analytics_events', [
            'booking_id' => $booking->getKey(),
            'event_type' => AnalyticsEventType::CompletedBooking->value,
            'traffic_source' => 'facebook',
        ]);
    }

    public function test_owner_analytics_are_tenant_and_venue_scoped(): void
    {
        [$organizationA, $venueA, $resourceA, $ownerA] = $this->setupInventory('analytics-owner-a');
        [, $venueA2, $resourceA2] = $this->addVenue($organizationA, 'analytics-owner-a-second');
        [$organizationB, $venueB, $resourceB] = $this->setupInventory('analytics-owner-b');
        AnalyticsEvent::factory()->for($venueA)->count(2)->create([
            'organization_id' => $organizationA->getKey(),
            'event_type' => AnalyticsEventType::VenueProfileView,
        ]);
        AnalyticsEvent::factory()->for($venueA2)->create([
            'organization_id' => $organizationA->getKey(),
            'event_type' => AnalyticsEventType::VenueProfileView,
        ]);
        AnalyticsEvent::factory()->for($venueB)->count(7)->create([
            'organization_id' => $organizationB->getKey(),
            'event_type' => AnalyticsEventType::VenueProfileView,
        ]);
        $this->booking($organizationA, $venueA, $resourceA, PaymentStatus::Paid);
        $this->booking($organizationA, $venueA2, $resourceA2, PaymentStatus::Paid);
        $this->booking($organizationB, $venueB, $resourceB, PaymentStatus::Paid);

        $this->actingAs($ownerA)->get(route('owner.analytics', [
            'venue' => $venueA->getKey(),
        ]))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Owner/Analytics/Index')
            ->where('report.metrics.profile_views', 2)
            ->where('report.metrics.completed_bookings', 1)
            ->where('report.metrics.booking_revenue', '650.00')
            ->where('filters.venue', $venueA->getKey()));

        $this->actingAs($ownerA)->get(route('owner.analytics', ['venue' => $venueB->getKey()]))
            ->assertNotFound();
    }

    public function test_revenue_customer_and_date_metrics_use_authoritative_booking_states(): void
    {
        [$organization, $venue, $resource, $owner] = $this->setupInventory('analytics-states');
        $returning = User::factory()->create();
        $new = User::factory()->create();
        $this->booking($organization, $venue, $resource, PaymentStatus::Paid, $returning, now()->subDays(60));
        $this->booking($organization, $venue, $resource, PaymentStatus::Paid, $returning, now()->subDays(2));
        $this->booking($organization, $venue, $resource, PaymentStatus::Pending, $new, now()->subDay());
        $this->booking($organization, $venue, $resource, PaymentStatus::Refunded, User::factory()->create(), now()->subDay());
        $this->booking($organization, $venue, $resource, PaymentStatus::Failed, User::factory()->create(), now()->subDay());
        $this->booking(
            $organization,
            $venue,
            $resource,
            PaymentStatus::Paid,
            User::factory()->create(),
            now()->subDay(),
            BookingStatus::Cancelled,
        );

        $this->actingAs($owner)->get(route('owner.analytics', [
            'from' => now()->subDays(30)->toDateString(),
            'to' => now()->toDateString(),
        ]))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('report.metrics.booking_starts', 5)
            ->where('report.metrics.completed_bookings', 2)
            ->where('report.metrics.booking_revenue', '1300.00')
            ->where('report.metrics.new_customers', 1)
            ->where('report.metrics.returning_customers', 1));
    }

    public function test_promotion_metrics_and_booking_source_attribution_are_reported(): void
    {
        [$organization, $venue, $resource, $owner] = $this->setupInventory('analytics-promotion');
        $promotion = Promotion::factory()->for($venue)->create([
            'organization_id' => $organization->getKey(),
            'resource_id' => $resource->getKey(),
            'title' => 'Tracked campaign',
        ]);
        AnalyticsEvent::factory()->for($venue)->for($promotion)->count(3)->create([
            'organization_id' => $organization->getKey(),
            'event_type' => AnalyticsEventType::PromotionImpression,
        ]);
        AnalyticsEvent::factory()->for($venue)->for($promotion)->create([
            'organization_id' => $organization->getKey(),
            'event_type' => AnalyticsEventType::PromotionClick,
        ]);
        $booking = $this->booking($organization, $venue, $resource, PaymentStatus::Paid);
        $booking->update([
            'promotion_id' => $promotion->getKey(),
            'promotion_campaign_token' => $promotion->campaign_token,
            'promotion_title' => $promotion->title,
            'traffic_source' => 'promotion',
            'traffic_source_detail' => $promotion->campaign_token,
        ]);

        $this->actingAs($owner)->get(route('owner.analytics'))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('report.promotions.0.title', 'Tracked campaign')
            ->where('report.promotions.0.impressions', 3)
            ->where('report.promotions.0.clicks', 1)
            ->where('report.promotions.0.bookings', 1)
            ->where('report.promotions.0.revenue', '650.00')
            ->where('report.traffic_sources.0.source', 'marketplace_promotion'));
    }

    public function test_only_platform_admin_can_view_cross_tenant_metrics(): void
    {
        [$organization, $venue, $resource, $owner] = $this->setupInventory('platform-analytics');
        $this->booking($organization, $venue, $resource, PaymentStatus::Paid);
        $platform = User::factory()->create(['is_platform_admin' => true]);

        $this->actingAs($owner)->get(route('platform.analytics'))->assertForbidden();
        $this->actingAs($platform)->get(route('platform.analytics'))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Platform/Analytics/Index')
            ->where('report.metrics.completed_bookings', 1)
            ->where('report.metrics.booking_revenue', '650.00')
            ->has('report.organizations', 1));
    }

    public function test_platform_analytics_reports_venue_level_visitor_counts(): void
    {
        [$organizationA, $venueA, $resourceA] = $this->setupInventory('platform-venue-traffic-a');
        [$organizationB, $venueB] = $this->setupInventory('platform-venue-traffic-b');
        $directoryListing = VenueDirectoryListing::factory()->published()->create([
            'name' => 'Guide Only Pickleball Center',
            'city' => 'Davao City',
            'province' => 'Davao del Sur',
            'slug' => 'guide-only-pickleball-center',
            'email' => 'private-owner@example.com',
        ]);
        $directoryListing->sports()->attach(Sport::query()->firstOrCreate(
            ['slug' => 'pickleball'],
            ['name' => 'Pickleball', 'is_active' => true],
        ));
        $platform = User::factory()->platformAdmin()->create();

        foreach (['repeat-visitor', 'repeat-visitor', 'new-visitor'] as $visitor) {
            AnalyticsEvent::factory()->for($venueA)->create([
                'organization_id' => $organizationA->getKey(),
                'event_type' => AnalyticsEventType::VenueProfileView,
                'visitor_hash' => hash('sha256', $visitor),
            ]);
        }

        AnalyticsEvent::factory()->for($venueA)->count(2)->create([
            'organization_id' => $organizationA->getKey(),
            'event_type' => AnalyticsEventType::VenueImpression,
        ]);
        AnalyticsEvent::factory()->for($venueA)->for($resourceA, 'resource')->create([
            'organization_id' => $organizationA->getKey(),
            'event_type' => AnalyticsEventType::AvailabilityView,
        ]);
        AnalyticsEvent::factory()->for($venueB)->create([
            'organization_id' => $organizationB->getKey(),
            'event_type' => AnalyticsEventType::VenueProfileView,
            'visitor_hash' => hash('sha256', 'venue-b-visitor'),
        ]);
        foreach (['directory-visitor-a', 'directory-visitor-b'] as $visitor) {
            AnalyticsEvent::factory()->create([
                'organization_id' => null,
                'venue_id' => null,
                'venue_directory_listing_id' => $directoryListing->getKey(),
                'event_type' => AnalyticsEventType::VenueProfileView,
                'visitor_hash' => hash('sha256', $visitor),
            ]);
        }
        $this->booking($organizationA, $venueA, $resourceA, PaymentStatus::Paid);

        $this->actingAs($platform)->get(route('platform.analytics'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('report.venues', 3)
                ->where('report.venues.0.name', $venueA->name)
                ->where('report.venues.0.organization', $organizationA->name)
                ->where('report.venues.0.booking_status', 'Bookable on FinACourt')
                ->where('report.venues.0.unique_visitors', 2)
                ->where('report.venues.0.profile_views', 3)
                ->where('report.venues.0.impressions', 2)
                ->where('report.venues.0.availability_views', 1)
                ->where('report.venues.0.bookings', 1)
                ->where('report.venues.0.revenue', '650.00')
                ->where('report.venues.1.name', 'Guide Only Pickleball Center')
                ->where('report.venues.1.organization', 'Not joined yet')
                ->where('report.venues.1.booking_status', 'Not bookable yet')
                ->where('report.venues.1.public_url', '/directory/guide-only-pickleball-center')
                ->where('report.venues.1.unique_visitors', 2)
                ->where('report.venues.1.profile_views', 2)
                ->where('report.venues.1.bookings', 0)
                ->where('report.venues.1.revenue', '0.00')
                ->where('report.venues.2.name', $venueB->name)
                ->where('report.venues.2.unique_visitors', 1)
                ->missing('report.venues.0.visitor_hash')
                ->missing('report.venues.1.visitor_hash')
                ->missing('report.venues.0.email'));
    }

    public function test_platform_analytics_exposes_owner_acquisition_evidence_without_visitor_identity(): void
    {
        $prospectOrganization = Organization::factory()->create();
        [, $prospectVenue, $resource] = $this->addVenue($prospectOrganization, 'unclaimed-prospect');
        $prospectVenue->update([
            'name' => 'Unclaimed Makati Courts',
            'claimed_at' => null,
            'city' => 'Makati',
            'city_slug' => 'makati',
        ]);
        $platform = User::factory()->platformAdmin()->create();

        foreach ([
            ['visitor' => 'searcher-a', 'metadata' => ['city' => 'makati', 'sport' => 'badminton', 'result_count' => 0]],
            ['visitor' => 'searcher-b', 'metadata' => ['city' => 'makati', 'sport' => 'badminton', 'date' => now()->addDay()->toDateString(), 'start_time' => '18:00', 'result_count' => 2]],
            ['visitor' => 'searcher-c', 'metadata' => ['city' => 'pasig', 'sport' => 'pickleball', 'result_count' => 0]],
        ] as $search) {
            $outcome = $search['metadata']['result_count'] > 0
                ? DemandSearchOutcome::ResultsAvailable
                : DemandSearchOutcome::NoResults;
            AnalyticsEvent::factory()->marketplaceSearch()->create([
                'visitor_hash' => hash('sha256', $search['visitor']),
                'demand_city_slug' => $search['metadata']['city'],
                'demand_sport_slug' => $search['metadata']['sport'],
                'requested_date' => $search['metadata']['date'] ?? null,
                'requested_start_time' => $search['metadata']['start_time'] ?? null,
                'matching_venue_count' => $search['metadata']['result_count'],
                'available_result_count' => $search['metadata']['result_count'],
                'search_outcome' => $outcome,
                'metadata' => $search['metadata'],
            ]);
        }

        AnalyticsEvent::factory()->for($prospectVenue)->count(3)->create([
            'organization_id' => $prospectOrganization->getKey(),
            'event_type' => AnalyticsEventType::VenueImpression,
        ]);
        AnalyticsEvent::factory()->for($prospectVenue)->count(2)->create([
            'organization_id' => $prospectOrganization->getKey(),
            'event_type' => AnalyticsEventType::VenueProfileView,
        ]);
        AnalyticsEvent::factory()->for($prospectVenue)->for($resource, 'resource')->create([
            'organization_id' => $prospectOrganization->getKey(),
            'event_type' => AnalyticsEventType::AvailabilityView,
        ]);

        $this->actingAs($platform)->get(route('platform.analytics'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('acquisition.metrics.searches', 3)
                ->where('acquisition.metrics.unique_searchers', 3)
                ->where('acquisition.metrics.high_intent_searches', 1)
                ->where('acquisition.metrics.zero_result_searches', 2)
                ->where('acquisition.supply.unclaimed_venues', 1)
                ->where('acquisition.demand_segments.0.city', 'Makati')
                ->where('acquisition.demand_segments.0.sport', 'Badminton')
                ->where('acquisition.demand_segments.0.searches', 2)
                ->where('acquisition.demand_segments.0.zero_results', 1)
                ->where('acquisition.prospect_venues.0.name', 'Unclaimed Makati Courts')
                ->where('acquisition.prospect_venues.0.profile_views', 2)
                ->where('acquisition.prospect_venues.0.availability_views', 1)
                ->missing('acquisition.prospect_venues.0.email')
                ->missing('acquisition.prospect_venues.0.visitor_hash'));
    }

    /** @return array{Organization, Venue, CourtResource, User} */
    private function setupInventory(string $slug): array
    {
        $organization = Organization::factory()->create(['timezone' => 'Asia/Manila']);
        $owner = User::factory()->create();
        Membership::factory()->owner()->for($owner)->for($organization)->create();
        [, $venue, $resource] = $this->addVenue($organization, $slug);

        return [$organization, $venue, $resource, $owner];
    }

    /** @return array{Organization, Venue, CourtResource} */
    private function addVenue(Organization $organization, string $slug): array
    {
        $venue = Venue::factory()->for($organization)->published()->create([
            'slug' => $slug,
            'city' => 'Makati',
            'city_slug' => 'makati',
        ]);
        $sport = Sport::query()->firstOrCreate(
            ['slug' => 'badminton'],
            ['name' => 'Badminton', 'is_active' => true],
        );
        $resource = CourtResource::factory()->for($venue)->for($sport)->create([
            'base_hourly_rate' => '650.00',
            'booking_increment_minutes' => 60,
        ]);

        foreach (range(0, 6) as $day) {
            OperatingHour::factory()->for($venue)->create([
                'day_of_week' => $day,
                'opens_at' => '08:00',
                'closes_at' => '22:00',
            ]);
        }

        return [$organization, $venue, $resource];
    }

    private function booking(
        Organization $organization,
        Venue $venue,
        CourtResource $resource,
        PaymentStatus $paymentStatus,
        ?User $player = null,
        mixed $createdAt = null,
        BookingStatus $status = BookingStatus::Confirmed,
    ): Booking {
        return Booking::factory()->for($resource, 'resource')->create([
            'organization_id' => $organization->getKey(),
            'venue_id' => $venue->getKey(),
            'player_user_id' => ($player ?? User::factory()->create())->getKey(),
            'source' => BookingSource::Marketplace,
            'status' => $status,
            'payment_status' => $paymentStatus,
            'traffic_source' => 'direct',
            'total_amount' => '650.00',
            'created_at' => $createdAt ?? now(),
            'updated_at' => $createdAt ?? now(),
        ]);
    }
}
