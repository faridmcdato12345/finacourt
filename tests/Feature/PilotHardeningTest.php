<?php

namespace Tests\Feature;

use App\Enums\AcquisitionSource;
use App\Enums\AnalyticsEventType;
use App\Enums\BookingStatus;
use App\Enums\MembershipRole;
use App\Enums\PaymentStatus;
use App\Enums\PromotionDiscountType;
use App\Enums\PromotionType;
use App\Enums\ResourceSetting;
use App\Enums\ResourceType;
use App\Models\Amenity;
use App\Models\AnalyticsEvent;
use App\Models\Booking;
use App\Models\CourtResource;
use App\Models\Membership;
use App\Models\OperatingHour;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Promotion;
use App\Models\Sport;
use App\Models\User;
use App\Models\Venue;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PilotHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_readiness_security_headers_csp_nonce_and_request_ids_are_present(): void
    {
        config(['security.content_security_policy' => true]);

        $ready = $this->withHeader('X-Request-ID', 'pilot-request-1234')
            ->get(route('health.ready'));

        $ready->assertOk()
            ->assertJson(['status' => 'ready'])
            ->assertHeader('X-Request-ID', 'pilot-request-1234')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(self)');
        $this->assertStringContainsString('no-store', (string) $ready->headers->get('Cache-Control'));

        $private = $this->get(route('login'));
        $private->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
        $csp = (string) $private->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringContainsString("img-src 'self' data: https://tile.openstreetmap.org", $csp);
        $this->assertMatchesRegularExpression("~script-src 'self' 'nonce-[A-Za-z0-9]+'~", $csp);

        preg_match("/'nonce-([^']+)'/", $csp, $matches);
        $private->assertSee('nonce="'.$matches[1].'"', false);

        $generated = $this->withHeader('X-Request-ID', 'short')->get(route('health.ready'));
        $this->assertNotSame('short', $generated->headers->get('X-Request-ID'));
    }

    public function test_platform_admin_cannot_cross_associate_promotion_inventory_from_active_context(): void
    {
        [$organizationA, $venueA, $resourceA] = $this->inventory('alpha-venue');
        [$organizationB, $venueB, $resourceB] = $this->inventory('bravo-venue');
        $promotion = Promotion::factory()->for($venueB)->create([
            'organization_id' => $organizationB->getKey(),
            'resource_id' => $resourceB->getKey(),
        ]);
        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($admin)
            ->withSession(['tenant.organization_id' => $organizationA->getKey()])
            ->put(route('owner.promotions.update', $promotion), $this->promotionData($venueA, $resourceA))
            ->assertSessionHasErrors('venue_id');

        $promotion->refresh();
        $this->assertSame($organizationB->getKey(), $promotion->organization_id);
        $this->assertSame($venueB->getKey(), $promotion->venue_id);
        $this->assertSame($resourceB->getKey(), $promotion->resource_id);

        $this->actingAs($admin)
            ->withSession(['tenant.organization_id' => $organizationA->getKey()])
            ->put(route('owner.promotions.update', $promotion), [
                ...$this->promotionData($venueB, $resourceB),
                'title' => 'Explicit cross-tenant admin correction',
            ])->assertRedirect(route('owner.promotions.show', $promotion));

        $this->assertDatabaseHas('promotions', [
            'id' => $promotion->getKey(),
            'organization_id' => $organizationB->getKey(),
            'venue_id' => $venueB->getKey(),
            'title' => 'Explicit cross-tenant admin correction',
        ]);
    }

    public function test_browser_mass_assignment_cannot_move_owned_records_between_tenants(): void
    {
        [$organizationA, $venueA, $resourceA, $sportA, $ownerA] = $this->inventory('owned-venue', true);
        [$organizationB, $venueB] = $this->inventory('other-venue');
        $amenity = Amenity::factory()->create();
        $promotion = Promotion::factory()->for($venueA)->create([
            'organization_id' => $organizationA->getKey(),
            'resource_id' => $resourceA->getKey(),
        ]);

        $this->actingAs($ownerA)->put(route('owner.venues.update', $venueA), [
            ...$this->venueData($sportA, [$amenity->getKey()]),
            'organization_id' => $organizationB->getKey(),
            'verified_at' => now()->toISOString(),
            'claimed_at' => null,
        ])->assertRedirect();

        $this->actingAs($ownerA)->put(route('owner.venues.resources.update', [$venueA, $resourceA]), [
            ...$this->resourceData($sportA),
            'venue_id' => $venueB->getKey(),
            'currency' => 'USD',
        ])->assertRedirect();

        $this->actingAs($ownerA)->put(route('owner.promotions.update', $promotion), [
            ...$this->promotionData($venueA, $resourceA),
            'organization_id' => $organizationB->getKey(),
            'campaign_token' => 'BROWSER-REPLACEMENT',
        ])->assertRedirect();

        $this->assertSame($organizationA->getKey(), $venueA->refresh()->organization_id);
        $this->assertNull($venueA->verified_at);
        $this->assertNotNull($venueA->claimed_at);
        $this->assertSame($venueA->getKey(), $resourceA->refresh()->venue_id);
        $this->assertSame('PHP', $resourceA->currency);
        $this->assertSame($organizationA->getKey(), $promotion->refresh()->organization_id);
        $this->assertNotSame('BROWSER-REPLACEMENT', $promotion->campaign_token);
    }

    public function test_owner_idor_attempts_fail_across_inventory_booking_payment_and_analytics_routes(): void
    {
        [$organizationA, , , , $ownerA] = $this->inventory('tenant-a', true);
        [$organizationB, $venueB, $resourceB] = $this->inventory('tenant-b');
        $promotionB = Promotion::factory()->for($venueB)->create([
            'organization_id' => $organizationB->getKey(),
            'resource_id' => $resourceB->getKey(),
        ]);
        $bookingB = Booking::factory()->for($resourceB, 'resource')->create([
            'organization_id' => $organizationB->getKey(),
            'venue_id' => $venueB->getKey(),
            'status' => BookingStatus::Confirmed,
        ]);
        Payment::factory()->for($bookingB)->create(['organization_id' => $organizationB->getKey()]);

        $this->actingAs($ownerA)->withSession(['tenant.organization_id' => $organizationA->getKey()]);
        $this->get(route('owner.venues.show', $venueB))->assertForbidden();
        $this->get(route('owner.venues.hours.edit', $venueB))->assertForbidden();
        $this->get(route('owner.venues.resources.edit', [$venueB, $resourceB]))->assertForbidden();
        $this->get(route('owner.promotions.show', $promotionB))->assertForbidden();
        $this->get(route('owner.analytics', ['venue' => $venueB->getKey()]))->assertNotFound();
        $this->patch(route('owner.bookings.cancel', $bookingB), [])->assertNotFound();
        $this->patch(route('owner.bookings.payment.update', $bookingB), [
            'status' => PaymentStatus::Paid->value,
        ])->assertNotFound();
        $this->post(route('owner.organizations.activate', $organizationB))->assertForbidden();
    }

    public function test_demo_seed_is_local_only_idempotent_and_covers_multiple_tenants_and_attribution(): void
    {
        config(['pilot.demo_seed_enabled' => true]);

        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('organizations', 12);
        $this->assertDatabaseHas('venues', ['slug' => 'demo-courts-makati', 'is_published' => true]);
        $this->assertDatabaseHas('venues', ['slug' => 'northside-sports-quezon-city', 'is_published' => true]);
        $demoVenue = Venue::query()->where('slug', 'demo-courts-makati')->firstOrFail();
        $this->assertSame(10, $demoVenue->resources()->count());
        $this->assertSame(11, Promotion::query()
            ->where('organization_id', $demoVenue->organization_id)
            ->where('venue_id', $demoVenue->getKey())
            ->whereNotNull('resource_id')
            ->where('is_active', true)
            ->where('is_public', true)
            ->count());
        $this->assertDatabaseHas('promotions', [
            'campaign_token' => 'DEAL-DEMO-TONIGHT-SLOTS',
            'goal' => 'promote_today_or_tonight',
            'status' => 'active',
            'targets_specific_slots' => true,
        ]);
        $this->assertSame(2, $demoVenue->promotions()
            ->where('campaign_token', 'DEAL-DEMO-TONIGHT-SLOTS')
            ->firstOrFail()
            ->slots()
            ->count());
        $singleCourtOwners = User::query()
            ->whereIn('email', collect(range(1, 10))->map(fn (int $number): string => sprintf('court.owner%02d@example.com', $number)))
            ->with('memberships.organization.venues.resources')
            ->get();
        $this->assertCount(10, $singleCourtOwners);

        foreach ($singleCourtOwners as $singleCourtOwner) {
            $this->assertCount(1, $singleCourtOwner->memberships);
            $this->assertSame(MembershipRole::Owner, $singleCourtOwner->memberships->first()->role);
            $this->assertCount(1, $singleCourtOwner->memberships->first()->organization->venues);
            $this->assertCount(1, $singleCourtOwner->memberships->first()->organization->venues->first()->resources);
        }

        $this->assertDatabaseCount('resources', 21);
        $this->assertDatabaseCount('promotions', 11);
        $this->assertDatabaseCount('promotion_slots', 2);
        $this->assertDatabaseHas('bookings', [
            'reference' => 'BK-DEMO-PROMO1',
            'status' => BookingStatus::Confirmed->value,
            'traffic_source' => AcquisitionSource::MarketplacePromotion->value,
        ]);
        $this->assertDatabaseHas('booking_attributions', [
            'attributed_source' => AcquisitionSource::MarketplacePromotion->value,
            'promotion_campaign_token' => 'DEAL-DEMO-WEEKDAY20',
        ]);
        $this->assertDatabaseHas('analytics_events', [
            'event_type' => 'completed_booking',
        ]);
        $this->assertSame(12, AnalyticsEvent::query()
            ->where('event_type', AnalyticsEventType::MarketplaceSearch)
            ->count());
        $this->assertSame(19, AnalyticsEvent::query()->count());
    }

    /** @return array{Organization, Venue, CourtResource, Sport, User|null} */
    private function inventory(string $slug, bool $withOwner = false): array
    {
        $organization = Organization::factory()->create();
        $venue = Venue::factory()->for($organization)->create([
            'slug' => $slug,
            'is_published' => true,
            'claimed_at' => now(),
        ]);
        $sport = Sport::factory()->create();
        $venue->sports()->attach($sport);
        $resource = CourtResource::factory()->for($venue)->for($sport)->create();

        foreach (range(0, 6) as $day) {
            OperatingHour::factory()->for($venue)->create([
                'day_of_week' => $day,
                'is_closed' => false,
                'opens_at' => '08:00',
                'closes_at' => '22:00',
            ]);
        }

        $owner = null;

        if ($withOwner) {
            $owner = User::factory()->create();
            Membership::factory()->owner()->for($owner)->for($organization)->create();
        }

        return [$organization, $venue, $resource, $sport, $owner];
    }

    /** @return array<string, mixed> */
    private function venueData(Sport $sport, array $amenities = []): array
    {
        return [
            'name' => 'Hardened Venue',
            'slug' => 'hardened-venue',
            'description' => 'Pilot hardening test venue.',
            'address' => '100 Secure Street',
            'city' => 'Makati',
            'province' => 'Metro Manila',
            'latitude' => null,
            'longitude' => null,
            'phone' => null,
            'email' => null,
            'website' => null,
            'is_published' => true,
            'sports' => [$sport->getKey()],
            'amenities' => $amenities,
        ];
    }

    /** @return array<string, mixed> */
    private function resourceData(Sport $sport): array
    {
        return [
            'name' => 'Court One',
            'sport_id' => $sport->getKey(),
            'resource_type' => ResourceType::Court->value,
            'setting' => ResourceSetting::Indoor->value,
            'is_active' => true,
            'base_hourly_rate' => '700.00',
            'booking_increment_minutes' => 60,
        ];
    }

    /** @return array<string, mixed> */
    private function promotionData(Venue $venue, CourtResource $resource): array
    {
        return [
            'venue_id' => $venue->getKey(),
            'resource_id' => $resource->getKey(),
            'title' => 'Pilot deal',
            'description' => 'A validated local deal.',
            'promotion_type' => PromotionType::Deal->value,
            'discount_type' => PromotionDiscountType::Percentage->value,
            'discount_value' => '20.00',
            'starts_on' => now()->toDateString(),
            'ends_on' => now()->addMonth()->toDateString(),
            'days_of_week' => [],
            'starts_at_time' => null,
            'ends_at_time' => null,
            'is_active' => true,
            'is_public' => true,
        ];
    }
}
