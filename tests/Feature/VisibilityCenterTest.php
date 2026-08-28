<?php

namespace Tests\Feature;

use App\Enums\AcquisitionSource;
use App\Enums\VisibilityLinkDestination;
use App\Models\Booking;
use App\Models\CourtResource;
use App\Models\Membership;
use App\Models\OperatingHour;
use App\Models\Organization;
use App\Models\Sport;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenuePhoto;
use App\Visibility\Contracts\PlacesProvider;
use App\Visibility\GoogleDirections;
use App\Visibility\PlaceCandidate;
use App\Visibility\VisibilityScore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class VisibilityCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_visibility_score_is_deterministic_and_explains_every_point(): void
    {
        [, $venue] = $this->inventory('complete-visibility', complete: true);
        $report = app(VisibilityScore::class)->forVenue($venue);

        $this->assertSame(100, $report['score']);
        $this->assertSame(100, collect($report['checks'])->sum('weight'));
        $this->assertTrue(collect($report['checks'])->every('complete'));

        $venue->update(['description' => null, 'phone' => null, 'email' => null, 'website' => null]);
        $incomplete = app(VisibilityScore::class)->forVenue($venue->fresh());

        $this->assertSame(80, $incomplete['score']);
        $this->assertFalse(collect($incomplete['checks'])->firstWhere('code', 'description')['complete']);
        $this->assertFalse(collect($incomplete['checks'])->firstWhere('code', 'contact')['complete']);
    }

    public function test_owner_visibility_center_is_tenant_scoped_and_staff_needs_inventory_permission(): void
    {
        [$ownerA, $venueA] = $this->inventory('tenant-a-visibility', complete: true);
        [, $venueB] = $this->inventory('tenant-b-visibility', complete: true);
        $staff = User::factory()->create();
        Membership::factory()->for($staff)->for($venueA->organization)->create();

        $this->actingAs($ownerA)->get(route('owner.visibility.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Owner/Visibility/Index')
                ->has('venues', 1)
                ->where('venues.0.id', $venueA->getKey())
                ->where('venues.0.score', 100)
                ->where('integrations.places.available', false)
                ->where('integrations.business_profile.available', false)
                ->missing('venues.1'));

        $this->actingAs($staff)->get(route('owner.visibility.index'))->assertForbidden();
        $this->assertNotSame($venueA->organization_id, $venueB->organization_id);
    }

    public function test_stable_qr_links_are_tenant_bound_generate_svg_and_record_trusted_attribution(): void
    {
        [$ownerA, $venueA, $resourceA] = $this->inventory('qr-visibility-a', complete: true);
        [, $venueB] = $this->inventory('qr-visibility-b', complete: true);

        $this->actingAs($ownerA)->post(route('owner.venues.visibility-links.store', $venueA), [
            'destination' => VisibilityLinkDestination::Booking->value,
        ])->assertRedirect();
        $this->actingAs($ownerA)->post(route('owner.venues.visibility-links.store', $venueB), [
            'destination' => VisibilityLinkDestination::Venue->value,
        ])->assertForbidden();

        $link = $venueA->visibilityLinks()->firstOrFail();
        $this->get(route('visibility-links.qr', $link->token))
            ->assertOk()
            ->assertHeader('content-type', 'image/svg+xml')
            ->assertSee('<svg', false);
        $this->get(route('visibility-links.visit', $link->token))
            ->assertRedirect(route('marketplace.venues.show', $venueA->slug).'#availability')
            ->assertSessionHas('analytics.acquisition_context.last_touch.source', AcquisitionSource::QrCode->value)
            ->assertSessionHas('analytics.acquisition_context.last_touch.campaign', $link->token);

        $player = User::factory()->create();
        $date = now($venueA->organization->timezone)->addDays(5)->toDateString();
        $this->actingAs($player)->post(route('player.bookings.store', $venueA->slug), [
            'resource_id' => $resourceA->getKey(),
            'booking_date' => $date,
            'start_time' => '09:00',
            'duration_minutes' => 60,
            'customer_name' => 'QR Player',
            'terms' => '1',
        ])->assertSessionHasNoErrors()->assertRedirect();
        $booking = Booking::query()->where('player_user_id', $player->getKey())->firstOrFail();
        $this->assertSame(AcquisitionSource::QrCode, $booking->attribution->attributed_source);
        $this->assertSame($link->token, $booking->attribution->attributed_campaign);

        $this->assertSame(1, $link->fresh()->visits_count);
        $this->assertDatabaseCount('visibility_links', 1);

        $venueA->update(['is_published' => false]);
        $this->get(route('visibility-links.visit', $link->token))->assertNotFound();
    }

    public function test_directions_prefers_a_verified_place_id_then_verified_coordinates_then_address(): void
    {
        [, $venue] = $this->inventory('directions-visibility', complete: true);
        $directions = app(GoogleDirections::class);

        $coordinatesUrl = $directions->forVenue($venue);
        $this->assertStringContainsString('api=1', $coordinatesUrl);
        $this->assertStringContainsString('destination=14.5547000%2C121.0244000', $coordinatesUrl);
        $this->assertStringNotContainsString('destination_place_id', $coordinatesUrl);

        $venue->forceFill([
            'google_place_id' => 'ChIJ-verified-place',
            'google_place_id_source' => 'google_places',
            'google_place_id_verified_at' => now(),
        ])->save();
        $placeUrl = $directions->forVenue($venue->fresh());
        $this->assertStringContainsString('destination_place_id=ChIJ-verified-place', $placeUrl);

        $venue->forceFill([
            'latitude' => null,
            'longitude' => null,
            'coordinates_verified_at' => null,
            'google_place_id' => null,
            'google_place_id_source' => null,
            'google_place_id_verified_at' => null,
        ])->save();
        $addressUrl = $directions->forVenue($venue->fresh());
        $this->assertStringContainsString('Makati', $addressUrl);
    }

    public function test_place_facts_are_persisted_only_from_a_configured_provider_and_owner_confirmation(): void
    {
        [$owner, $venue] = $this->inventory('place-persistence');
        [, $otherVenue] = $this->inventory('other-place-persistence');
        $this->app->instance(PlacesProvider::class, new class implements PlacesProvider
        {
            public function available(): bool
            {
                return true;
            }

            public function resolve(string $reference): ?PlaceCandidate
            {
                return $reference === 'opaque-provider-reference'
                    ? new PlaceCandidate('ChIJ-provider-result', '10 Verified Avenue', 14.5995, 120.9842)
                    : null;
            }
        });

        $this->actingAs($owner)->post(route('owner.venues.google-place.store', $venue), [
            'place_reference' => 'opaque-provider-reference',
            'google_place_id' => 'browser-invented-id',
            'latitude' => 0,
            'longitude' => 0,
        ])->assertRedirect();
        $this->actingAs($owner)->post(route('owner.venues.google-place.store', $otherVenue), [
            'place_reference' => 'opaque-provider-reference',
        ])->assertForbidden();

        $venue->refresh();
        $this->assertSame('ChIJ-provider-result', $venue->google_place_id);
        $this->assertSame('google_places', $venue->google_place_id_source);
        $this->assertSame('10 Verified Avenue', $venue->address);
        $this->assertSame('14.5995000', $venue->latitude);
        $this->assertSame('120.9842000', $venue->longitude);
        $this->assertNotNull($venue->google_place_id_verified_at);
    }

    public function test_no_google_fallback_does_not_block_or_mutate_the_venue(): void
    {
        [$owner, $venue] = $this->inventory('no-google-fallback');
        $originalAddress = $venue->address;

        $this->actingAs($owner)->post(route('owner.venues.google-place.store', $venue), [
            'place_reference' => 'browser-reference',
        ])->assertSessionHasErrors('place_reference');

        $venue->refresh();
        $this->assertSame($originalAddress, $venue->address);
        $this->assertNull($venue->google_place_id);
        $this->assertNull($venue->google_place_id_verified_at);
    }

    public function test_dedicated_google_booking_marker_uses_the_existing_google_maps_taxonomy(): void
    {
        [, $venue] = $this->inventory('google-attribution', complete: true);

        $this->get(route('marketplace.venues.show', [
            'venueSlug' => $venue->slug,
            'utm_source' => 'google',
            'utm_medium' => 'business-profile',
        ]))
            ->assertOk()
            ->assertSessionHas(
                'analytics.acquisition_context.last_touch.source',
                AcquisitionSource::GoogleMaps->value,
            );
    }

    /** @return array{User, Venue, CourtResource} */
    private function inventory(string $slug, bool $complete = false): array
    {
        $organization = Organization::factory()->create();
        $owner = User::factory()->create();
        Membership::factory()->owner()->for($owner)->for($organization)->create();
        $venue = Venue::factory()->for($organization)->published()->create([
            'name' => 'Makati Court '.$slug,
            'slug' => $slug,
            'description' => $complete
                ? 'A complete and welcoming sports venue profile with clear facilities, player guidance, and practical booking information.'
                : 'Short profile.',
            'address' => '88 Sports Road',
            'city' => 'Makati',
            'city_slug' => 'makati',
            'province' => 'Metro Manila',
            'province_slug' => 'metro-manila',
            'latitude' => $complete ? 14.5547 : null,
            'longitude' => $complete ? 121.0244 : null,
            'coordinates_source' => $complete ? 'owner' : null,
            'coordinates_verified_at' => $complete ? now() : null,
            'phone' => $complete ? '+63 917 000 0000' : null,
        ]);
        $sport = Sport::factory()->create(['is_active' => true]);
        $venue->sports()->attach($sport);
        $resource = CourtResource::factory()->for($venue)->for($sport)->create([
            'is_active' => true,
            'base_hourly_rate' => '700.00',
            'booking_increment_minutes' => 60,
        ]);

        foreach (range(0, 6) as $day) {
            OperatingHour::factory()->for($venue)->create([
                'day_of_week' => $day,
                'is_closed' => false,
                'opens_at' => '08:00',
                'closes_at' => '22:00',
            ]);
        }

        if ($complete) {
            VenuePhoto::factory()->for($venue)->count(3)->sequence(
                ['is_primary' => true, 'sort_order' => 1],
                ['is_primary' => false, 'sort_order' => 2],
                ['is_primary' => false, 'sort_order' => 3],
            )->create();
        }

        return [$owner, $venue, $resource];
    }
}
