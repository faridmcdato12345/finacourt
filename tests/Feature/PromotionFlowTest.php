<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Enums\PromotionDiscountType;
use App\Enums\PromotionType;
use App\Models\Booking;
use App\Models\CourtResource;
use App\Models\Membership;
use App\Models\OperatingHour;
use App\Models\Organization;
use App\Models\Promotion;
use App\Models\Sport;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenuePhoto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PromotionFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_edit_preview_and_delete_own_promotion(): void
    {
        [$organization, $venue, $resource, $owner] = $this->setupInventory();

        $this->actingAs($owner)->post(route('owner.promotions.store'), [
            ...$this->promotionData($venue, $resource),
            'campaign_token' => 'BROWSER-CONTROLLED',
        ])->assertRedirect();

        $promotion = Promotion::query()->firstOrFail();
        $this->assertSame($organization->getKey(), $promotion->organization_id);
        $this->assertStringStartsWith('DEAL-', $promotion->campaign_token);
        $this->assertNotSame('BROWSER-CONTROLLED', $promotion->campaign_token);

        $this->actingAs($owner)->get(route('owner.promotions.show', $promotion))
            ->assertOk()
            ->assertSee($promotion->title)
            ->assertSee($promotion->campaign_token);

        $this->actingAs($owner)->put(route('owner.promotions.update', $promotion), [
            ...$this->promotionData($venue, $resource),
            'title' => 'Updated owner deal',
            'discount_value' => '25.00',
        ])->assertRedirect(route('owner.promotions.show', $promotion));

        $this->assertDatabaseHas('promotions', [
            'id' => $promotion->getKey(),
            'title' => 'Updated owner deal',
            'discount_value' => '25.00',
        ]);

        $this->actingAs($owner)->delete(route('owner.promotions.destroy', $promotion))
            ->assertRedirect(route('owner.promotions.index'));
        $this->assertDatabaseMissing('promotions', ['id' => $promotion->getKey()]);
    }

    public function test_owner_cannot_read_edit_or_associate_another_tenants_promotion_inventory(): void
    {
        [$organizationA, $venueA, $resourceA, $ownerA] = $this->setupInventory();
        [, $venueB, $resourceB, $ownerB] = $this->setupInventory(['slug' => 'tenant-b-venue']);
        $promotionA = Promotion::factory()->for($venueA)->create([
            'organization_id' => $organizationA->getKey(),
            'resource_id' => $resourceA->getKey(),
        ]);

        $this->actingAs($ownerB)->get(route('owner.promotions.show', $promotionA))->assertForbidden();
        $this->actingAs($ownerB)->put(route('owner.promotions.update', $promotionA), [
            ...$this->promotionData($venueB, $resourceB),
            'title' => 'Cross-tenant overwrite',
        ])->assertForbidden();

        $this->actingAs($ownerA)->post(route('owner.promotions.store'), [
            ...$this->promotionData($venueA, $resourceB),
        ])->assertSessionHasErrors('resource_id');

        $this->assertSame($venueA->getKey(), $promotionA->refresh()->venue_id);
        $this->assertSame(1, Promotion::query()->count());
    }

    public function test_applicable_promotion_sets_server_calculated_price_payment_and_attribution_snapshots(): void
    {
        [$organization, $venue, $resource] = $this->setupInventory();
        $promotion = Promotion::factory()->for($venue)->create([
            'organization_id' => $organization->getKey(),
            'resource_id' => $resource->getKey(),
            'title' => 'Twenty percent court deal',
            'discount_value' => '20.00',
        ]);
        $player = User::factory()->create();

        $booking = $this->createHold($player, $venue, $resource, [
            'campaign' => $promotion->campaign_token,
            'promotion_id' => 999999,
            'discount_value' => '99.00',
            'unit_price' => '0.01',
            'total_amount' => '0.01',
        ]);

        $this->assertSame($promotion->getKey(), $booking->promotion_id);
        $this->assertSame($promotion->campaign_token, $booking->promotion_campaign_token);
        $this->assertSame($promotion->title, $booking->promotion_title);
        $this->assertSame('650.00', $booking->original_unit_price);
        $this->assertSame('520.00', $booking->unit_price);
        $this->assertSame('650.00', $booking->original_total_amount);
        $this->assertSame('520.00', $booking->total_amount);
        $this->assertSame('130.00', $booking->discount_amount);
        $this->assertSame('520.00', $booking->payment->amount);
        $this->assertSame(PaymentStatus::Pending, $booking->payment->status);
        $this->assertSame(1, $promotion->refresh()->booking_starts_count);
    }

    public function test_fixed_hourly_rate_is_prorated_by_duration_without_stacking(): void
    {
        [$organization, $venue, $resource] = $this->setupInventory();
        $promotion = Promotion::factory()->for($venue)->create([
            'organization_id' => $organization->getKey(),
            'resource_id' => $resource->getKey(),
            'discount_type' => PromotionDiscountType::FixedHourlyRate,
            'discount_value' => '500.00',
        ]);
        $player = User::factory()->create();

        $booking = $this->createHold($player, $venue, $resource, [
            'campaign' => $promotion->campaign_token,
            'duration_minutes' => 120,
        ]);

        $this->assertSame('500.00', $booking->unit_price);
        $this->assertSame('1000.00', $booking->total_amount);
        $this->assertSame('1300.00', $booking->original_total_amount);
        $this->assertSame('300.00', $booking->discount_amount);
        $this->assertSame($promotion->getKey(), $booking->promotion_id);
    }

    public function test_inactive_expired_and_non_applicable_promotions_cannot_change_price(): void
    {
        [$organization, $venue, $resource] = $this->setupInventory();
        $player = User::factory()->create();
        $inactive = Promotion::factory()->inactive()->for($venue)->create([
            'organization_id' => $organization->getKey(),
            'resource_id' => $resource->getKey(),
        ]);
        $expired = Promotion::factory()->expired()->for($venue)->create([
            'organization_id' => $organization->getKey(),
            'resource_id' => $resource->getKey(),
        ]);
        $wrongTime = Promotion::factory()->for($venue)->create([
            'organization_id' => $organization->getKey(),
            'resource_id' => $resource->getKey(),
            'promotion_type' => PromotionType::TimeWindow,
            'starts_at_time' => '12:00',
            'ends_at_time' => '14:00',
        ]);

        foreach ([$inactive, $expired, $wrongTime] as $promotion) {
            $this->actingAs($player)->post(route('player.bookings.store', $venue->slug), [
                ...$this->holdData($resource),
                'campaign' => $promotion->campaign_token,
            ])->assertSessionHasErrors('campaign');
        }

        $this->assertDatabaseCount('bookings', 0);
        $booking = $this->createHold($player, $venue, $resource);
        $this->assertSame('650.00', $booking->unit_price);
        $this->assertSame('0.00', $booking->discount_amount);
        $this->assertNull($booking->promotion_id);
    }

    public function test_promotion_booking_snapshot_remains_correct_after_campaign_and_resource_changes(): void
    {
        [$organization, $venue, $resource, $owner] = $this->setupInventory();
        $promotion = Promotion::factory()->for($venue)->create([
            'organization_id' => $organization->getKey(),
            'resource_id' => $resource->getKey(),
            'title' => 'Original campaign title',
            'discount_value' => '20.00',
        ]);
        $booking = $this->createHold(User::factory()->create(), $venue, $resource, [
            'campaign' => $promotion->campaign_token,
        ]);

        $this->actingAs($owner)->put(route('owner.promotions.update', $promotion), [
            ...$this->promotionData($venue, $resource),
            'title' => 'Changed campaign title',
            'discount_value' => '50.00',
        ])->assertRedirect();
        $resource->update(['base_hourly_rate' => '1000.00']);

        $booking->refresh();
        $this->assertSame('Original campaign title', $booking->promotion_title);
        $this->assertSame('650.00', $booking->original_total_amount);
        $this->assertSame('520.00', $booking->total_amount);
        $this->assertSame('130.00', $booking->discount_amount);

        $this->actingAs($owner)->delete(route('owner.promotions.destroy', $promotion))->assertRedirect();
        $this->assertDatabaseHas('promotions', ['id' => $promotion->getKey()]);
    }

    public function test_campaign_token_is_carried_from_marketplace_to_booking_form(): void
    {
        [$organization, $venue, $resource] = $this->setupInventory();
        $promotion = Promotion::factory()->for($venue)->create([
            'organization_id' => $organization->getKey(),
            'resource_id' => $resource->getKey(),
        ]);
        $date = $this->futureDate();

        $venueResponse = $this->get(route('marketplace.venues.show', [
            'venueSlug' => $venue->slug,
            'campaign' => $promotion->campaign_token,
            'resource' => $resource->getKey(),
            'date' => $date,
            'duration' => 60,
        ]));
        $venueResponse->assertOk()->assertSee('campaign='.$promotion->campaign_token, false);

        $this->actingAs(User::factory()->create())->get(route('player.bookings.create', [
            'venueSlug' => $venue->slug,
            'resource' => $resource->getKey(),
            'date' => $date,
            'start' => '09:00',
            'duration' => 60,
            'campaign' => $promotion->campaign_token,
        ]))
            ->assertOk()
            ->assertSee($promotion->title)
            ->assertSee('name="campaign" value="'.$promotion->campaign_token.'"', false);

        $this->assertSame(1, $promotion->refresh()->clicks_count);
    }

    public function test_public_pages_show_only_current_public_promotions_from_public_inventory(): void
    {
        Storage::fake('public');
        [$organization, $venue, $resource] = $this->setupInventory();
        $cover = VenuePhoto::factory()->for($venue)->create([
            'storage_path' => "venues/{$venue->getKey()}/cover.jpg",
            'is_primary' => true,
            'sort_order' => 1,
        ]);
        Storage::disk('public')->put($cover->storage_path, 'image');
        $coverUrl = Storage::disk('public')->url($cover->storage_path);
        $active = Promotion::factory()->for($venue)->create([
            'organization_id' => $organization->getKey(),
            'resource_id' => $resource->getKey(),
            'title' => 'Visible active deal',
        ]);
        $inactive = Promotion::factory()->inactive()->for($venue)->create([
            'organization_id' => $organization->getKey(),
            'title' => 'Hidden inactive deal',
        ]);
        $private = Promotion::factory()->for($venue)->create([
            'organization_id' => $organization->getKey(),
            'title' => 'Hidden private deal',
            'is_public' => false,
        ]);
        [, $privateVenue, $privateResource] = $this->setupInventory([
            'slug' => 'unpublished-deal-venue',
            'is_published' => false,
        ]);
        Promotion::factory()->for($privateVenue)->create([
            'organization_id' => $privateVenue->organization_id,
            'resource_id' => $privateResource->getKey(),
            'title' => 'Hidden venue deal',
        ]);

        $this->get(route('marketplace.deals'))
            ->assertOk()
            ->assertSee($active->title)
            ->assertSee($coverUrl, false)
            ->assertDontSee($inactive->title)
            ->assertDontSee($private->title)
            ->assertDontSee('Hidden venue deal');
        $this->get(route('marketplace.venues.show', $venue->slug))
            ->assertOk()
            ->assertSee($active->title)
            ->assertDontSee($inactive->title)
            ->assertDontSee($private->title);
        $this->get(route('marketplace.courts.index'))
            ->assertOk()
            ->assertSee($active->title);
        $this->get(route('marketplace.home'))
            ->assertOk()
            ->assertSee('data-featured-deal', false)
            ->assertSee($active->title)
            ->assertSee($active->offerLabel())
            ->assertDontSee($inactive->title)
            ->assertDontSee($private->title);
        $this->get(route('marketplace.sitemap'))
            ->assertOk()
            ->assertSee(route('marketplace.deals'), false);
    }

    /** @param array<string, mixed> $venueAttributes
     * @return array{Organization, Venue, CourtResource, User}
     */
    private function setupInventory(array $venueAttributes = []): array
    {
        $organization = Organization::factory()->create(['timezone' => 'Asia/Manila']);
        $owner = User::factory()->create();
        Membership::factory()->owner()->for($owner)->for($organization)->create();
        $venue = Venue::factory()->for($organization)->published()->create([
            'name' => 'Promotion Test Courts',
            'slug' => 'promotion-test-courts',
            'city' => 'Makati',
            'city_slug' => 'makati',
            'province' => 'Metro Manila',
            'province_slug' => 'metro-manila',
            ...$venueAttributes,
        ]);
        $sport = Sport::query()->firstOrCreate(
            ['slug' => 'badminton'],
            ['name' => 'Badminton', 'is_active' => true],
        );
        $resource = CourtResource::factory()->for($venue)->for($sport)->create([
            'name' => 'Court One',
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

        return [$organization, $venue, $resource, $owner];
    }

    /** @return array<string, mixed> */
    private function promotionData(Venue $venue, ?CourtResource $resource = null): array
    {
        return [
            'venue_id' => $venue->getKey(),
            'resource_id' => $resource?->getKey(),
            'title' => 'Owner court deal',
            'description' => 'A real owner-managed promotion.',
            'promotion_type' => PromotionType::Deal->value,
            'discount_type' => PromotionDiscountType::Percentage->value,
            'discount_value' => '20.00',
            'starts_on' => now('Asia/Manila')->subDay()->toDateString(),
            'ends_on' => now('Asia/Manila')->addMonth()->toDateString(),
            'days_of_week' => [],
            'starts_at_time' => null,
            'ends_at_time' => null,
            'is_active' => true,
            'is_public' => true,
        ];
    }

    /** @param array<string, mixed> $extra */
    private function createHold(User $player, Venue $venue, CourtResource $resource, array $extra = []): Booking
    {
        $this->actingAs($player)->post(route('player.bookings.store', $venue->slug), [
            ...$this->holdData($resource),
            ...$extra,
        ])->assertRedirect();

        return Booking::query()
            ->where('player_user_id', $player->getKey())
            ->latest('id')
            ->with('payment')
            ->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function holdData(CourtResource $resource): array
    {
        return [
            'resource_id' => $resource->getKey(),
            'booking_date' => $this->futureDate(),
            'start_time' => '09:00',
            'duration_minutes' => 60,
            'customer_name' => 'Promo Player',
            'terms' => '1',
        ];
    }

    private function futureDate(): string
    {
        return now('Asia/Manila')->addDays(7)->toDateString();
    }
}
