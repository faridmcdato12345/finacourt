<?php

namespace Tests\Feature;

use App\Enums\AnalyticsEventType;
use App\Enums\BookingStatus;
use App\Enums\PromotionDiscountType;
use App\Enums\PromotionGoal;
use App\Enums\PromotionStatus;
use App\Enums\PromotionType;
use App\Models\AnalyticsEvent;
use App\Models\Booking;
use App\Models\CourtResource;
use App\Models\Membership;
use App\Models\OperatingHour;
use App\Models\Organization;
use App\Models\PlatformServiceFeeRule;
use App\Models\Promotion;
use App\Models\PromotionSlot;
use App\Models\Sport;
use App\Models\User;
use App\Models\Venue;
use App\Promotions\EmptySlotFinder;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PromotionEngineV2Test extends TestCase
{
    use RefreshDatabase;

    public function test_deal_goal_choices_include_plain_language_explanations(): void
    {
        [, , , $owner] = $this->setupInventory();
        PlatformServiceFeeRule::factory()->create([
            'percentage_basis_points' => 500,
            'created_by_user_id' => $owner->getKey(),
        ]);

        $this->actingAs($owner)
            ->get(route('owner.promotions.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Owner/Promotions/Create')
                ->has('goals', count(PromotionGoal::cases()))
                ->where('goals.0.label', PromotionGoal::FillEmptySlots->label())
                ->where('goals.0.description', PromotionGoal::FillEmptySlots->description())
                ->where('goals.4.description', PromotionGoal::PromoteSpecificSlots->description())
                ->where('serviceFee.type', 'percentage')
                ->where('serviceFee.percentage_basis_points', 500));
    }

    public function test_owner_creates_one_campaign_with_multiple_stable_eligible_slots(): void
    {
        [$organization, $venue, $resource, $owner] = $this->setupInventory();
        $date = $this->futureDate();

        $this->actingAs($owner)->post(route('owner.promotions.store'), $this->campaignData($venue, [
            'resource_id' => $resource->getKey(),
            'days_of_week' => [1, 3],
            'starts_at_time' => '06:00',
            'ends_at_time' => '11:59',
            'slots' => [
                $this->slotData($resource, $date, '09:00', '10:00'),
                $this->slotData($resource, $date, '10:00', '11:00'),
            ],
        ]))->assertRedirect();

        $promotion = Promotion::query()->with('slots')->firstOrFail();
        $this->assertSame($organization->getKey(), $promotion->organization_id);
        $this->assertSame(PromotionGoal::PromoteSpecificSlots, $promotion->goal);
        $this->assertSame(PromotionStatus::Active, $promotion->status);
        $this->assertTrue($promotion->targets_specific_slots);
        $this->assertNull($promotion->resource_id);
        $this->assertNull($promotion->audience_sport_id);
        $this->assertNull($promotion->days_of_week);
        $this->assertNull($promotion->starts_at_time);
        $this->assertNull($promotion->ends_at_time);
        $this->assertCount(2, $promotion->slots);
        $this->assertCount(2, $promotion->slots->pluck('slot_token')->unique());

        $tokens = $promotion->slots->pluck('slot_token', 'id');
        $this->actingAs($owner)->put(route('owner.promotions.update', $promotion), $this->campaignData($venue, [
            'resource_id' => $resource->getKey(),
            'title' => 'Updated multi-slot campaign',
            'slots' => $promotion->slots->map(fn (PromotionSlot $slot) => [
                'id' => $slot->getKey(),
                ...$this->slotData(
                    $resource,
                    $slot->slot_date->toDateString(),
                    substr($slot->starts_at_time, 0, 5),
                    substr($slot->ends_at_time, 0, 5),
                ),
            ])->all(),
        ]))->assertRedirect();

        $this->assertSame(
            $tokens->all(),
            $promotion->refresh()->slots()->pluck('slot_token', 'id')->all(),
        );
    }

    public function test_non_exact_strategy_cannot_retain_hidden_exact_slots(): void
    {
        [, $venue, $resource, $owner] = $this->setupInventory();
        $date = $this->futureDate();

        $this->actingAs($owner)->post(route('owner.promotions.store'), $this->campaignData($venue, [
            'resource_id' => $resource->getKey(),
            'goal' => PromotionGoal::IncreaseOffPeakBookings->value,
            'promotion_type' => PromotionType::TimeWindow->value,
            'starts_at_time' => '09:00',
            'ends_at_time' => '12:00',
            'slots' => [$this->slotData($resource, $date, '09:00', '10:00')],
        ]))->assertRedirect();

        $promotion = Promotion::query()->firstOrFail();
        $this->assertFalse($promotion->targets_specific_slots);
        $this->assertDatabaseCount('promotion_slots', 0);
    }

    public function test_publish_now_exposes_future_deals_without_discounting_ineligible_dates(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-04 12:00', 'Asia/Manila'));
        [$organization, $venue, $resource] = $this->setupInventory();
        $dealDate = '2026-09-06';
        $activePromotion = Promotion::factory()->for($venue)->create([
            'organization_id' => $organization->getKey(),
            'resource_id' => $resource->getKey(),
            'title' => 'Book Sunday early deal',
            'promotion_type' => PromotionType::SpecificSlots,
            'goal' => PromotionGoal::PromoteSpecificSlots,
            'status' => PromotionStatus::Active,
            'starts_on' => $dealDate,
            'ends_on' => $dealDate,
            'targets_specific_slots' => true,
        ]);
        PromotionSlot::factory()->for($activePromotion)->create(
            $this->slotData($resource, $dealDate, '09:00', '10:00'),
        );
        $scheduledPromotion = Promotion::factory()->for($venue)->create([
            'organization_id' => $organization->getKey(),
            'resource_id' => $resource->getKey(),
            'title' => 'Hidden until Sunday deal',
            'status' => PromotionStatus::Scheduled,
            'starts_on' => $dealDate,
            'ends_on' => $dealDate,
        ]);

        $this->get(route('marketplace.deals'))
            ->assertOk()
            ->assertSee($activePromotion->title)
            ->assertSee('Starts Sep 6')
            ->assertSee('Book for Sep 6')
            ->assertSee('date=2026-09-06', false)
            ->assertDontSee($scheduledPromotion->title);

        $this->get(route('marketplace.courts.index'))
            ->assertOk()
            ->assertSee($activePromotion->title)
            ->assertSee('data-effective-hourly-price="650.00"', false);

        $this->get(route('marketplace.courts.index', [
            'date' => $dealDate,
            'start_time' => '09:00',
            'duration_minutes' => 60,
        ]))->assertOk()
            ->assertSee($activePromotion->title)
            ->assertSee('data-effective-hourly-price="520.00"', false);

        $player = User::factory()->create();
        $this->actingAs($player)->post(route('player.bookings.store', $venue->slug), [
            ...$this->holdData($resource, '2026-09-04', '18:00'),
            'campaign' => $activePromotion->campaign_token,
        ])->assertSessionHasErrors('campaign');

        $this->actingAs($player)->post(route('player.bookings.store', $venue->slug), [
            ...$this->holdData($resource, $dealDate, '09:00'),
            'campaign' => $activePromotion->campaign_token,
        ])->assertRedirect();

        $booking = Booking::query()->firstOrFail();
        $this->assertSame('520.00', $booking->unit_price);
        $this->assertSame($activePromotion->getKey(), $booking->promotion_id);

        $this->travelTo(CarbonImmutable::parse('2026-09-06 08:00', 'Asia/Manila'));
        $this->get(route('marketplace.deals'))
            ->assertOk()
            ->assertSee($scheduledPromotion->title);

        $this->travelTo(CarbonImmutable::parse('2026-09-06 10:01', 'Asia/Manila'));
        $this->get(route('marketplace.deals'))
            ->assertOk()
            ->assertDontSee($activePromotion->title);
    }

    public function test_specific_slot_price_is_server_calculated_and_only_applies_inside_selected_window(): void
    {
        [$organization, $venue, $resource] = $this->setupInventory();
        $date = $this->futureDate();
        $promotion = Promotion::factory()->for($venue)->create([
            'organization_id' => $organization->getKey(),
            'resource_id' => $resource->getKey(),
            'goal' => PromotionGoal::PromoteSpecificSlots,
            'promotion_type' => PromotionType::SpecificSlots,
            'discount_value' => '20.00',
            'starts_on' => $date,
            'ends_on' => $date,
            'targets_specific_slots' => true,
        ]);
        PromotionSlot::factory()->for($promotion)->create(
            $this->slotData($resource, $date, '09:00', '11:00'),
        );
        $player = User::factory()->create();

        $this->actingAs($player)->post(route('player.bookings.store', $venue->slug), [
            ...$this->holdData($resource, $date, '09:00'),
            'campaign' => $promotion->campaign_token,
            'price' => '0.01',
            'discount_value' => '99.00',
        ])->assertRedirect();

        $booking = Booking::query()->firstOrFail();
        $this->assertSame('650.00', $booking->original_unit_price);
        $this->assertSame('520.00', $booking->unit_price);
        $this->assertSame('130.00', $booking->discount_amount);
        $this->assertSame($promotion->getKey(), $booking->promotion_id);

        $this->actingAs(User::factory()->create())->post(route('player.bookings.store', $venue->slug), [
            ...$this->holdData($resource, $date, '12:00'),
            'campaign' => $promotion->campaign_token,
        ])->assertSessionHasErrors('campaign');
        $this->assertDatabaseCount('bookings', 1);
    }

    public function test_exact_slot_is_authoritative_over_recurring_day_and_time_filters(): void
    {
        [$organization, $venue, $resource] = $this->setupInventory();
        $date = $this->futureDate();
        $selectedDay = CarbonImmutable::parse($date, 'Asia/Manila')->dayOfWeek;
        $promotion = Promotion::factory()->for($venue)->create([
            'organization_id' => $organization->getKey(),
            'resource_id' => $resource->getKey(),
            'goal' => PromotionGoal::PromoteSpecificSlots,
            'promotion_type' => PromotionType::SpecificSlots,
            'discount_value' => '20.00',
            'starts_on' => $date,
            'ends_on' => $date,
            'days_of_week' => [($selectedDay + 1) % 7],
            'starts_at_time' => '06:00:00',
            'ends_at_time' => '11:59:00',
            'targets_specific_slots' => true,
        ]);
        PromotionSlot::factory()->for($promotion)->create(
            $this->slotData($resource, $date, '08:00', '12:00'),
        );

        $this->actingAs(User::factory()->create())->post(route('player.bookings.store', $venue->slug), [
            ...$this->holdData($resource, $date, '11:00'),
            'campaign' => $promotion->campaign_token,
        ])->assertRedirect();

        $booking = Booking::query()->firstOrFail();
        $this->assertSame('520.00', $booking->unit_price);
        $this->assertSame('130.00', $booking->discount_amount);
        $this->assertSame($promotion->getKey(), $booking->promotion_id);
    }

    public function test_slot_validation_rejects_cross_tenant_outside_campaign_and_overlapping_windows(): void
    {
        [, $venueA, $resourceA, $ownerA] = $this->setupInventory();
        [, , $resourceB] = $this->setupInventory(['slug' => 'other-promotion-venue']);
        $date = $this->futureDate();

        $this->actingAs($ownerA)->post(route('owner.promotions.store'), $this->campaignData($venueA, [
            'slots' => [$this->slotData($resourceB, $date, '09:00', '10:00')],
        ]))->assertSessionHasErrors('slots.0.resource_id');

        $this->actingAs($ownerA)->post(route('owner.promotions.store'), $this->campaignData($venueA, [
            'slots' => [
                $this->slotData($resourceA, $date, '09:00', '11:00'),
                $this->slotData($resourceA, $date, '10:00', '12:00'),
            ],
        ]))->assertSessionHasErrors('slots.1.starts_at_time');

        $this->actingAs($ownerA)->post(route('owner.promotions.store'), $this->campaignData($venueA, [
            'ends_on' => $date,
            'slots' => [$this->slotData($resourceA, now('Asia/Manila')->addDays(8)->toDateString(), '09:00', '10:00')],
        ]))->assertSessionHasErrors('slots.0.slot_date');

        $this->assertDatabaseCount('promotions', 0);
        $this->assertDatabaseCount('promotion_slots', 0);
    }

    public function test_campaign_lifecycle_allows_safe_transitions_and_terminal_states_cannot_reopen(): void
    {
        [$organization, $venue, $resource, $owner] = $this->setupInventory();
        $promotion = Promotion::factory()->for($venue)->create([
            'organization_id' => $organization->getKey(),
            'resource_id' => $resource->getKey(),
        ]);

        $this->actingAs($owner)->put(route('owner.promotions.update', $promotion), $this->campaignData($venue, [
            'resource_id' => $resource->getKey(),
            'promotion_type' => PromotionType::Deal->value,
            'goal' => PromotionGoal::FillEmptySlots->value,
            'status' => PromotionStatus::Paused->value,
            'slots' => [],
        ]))->assertRedirect();
        $this->assertSame(PromotionStatus::Paused, $promotion->refresh()->status);
        $this->assertFalse($promotion->is_active);

        $this->actingAs($owner)->put(route('owner.promotions.update', $promotion), $this->campaignData($venue, [
            'resource_id' => $resource->getKey(),
            'promotion_type' => PromotionType::Deal->value,
            'goal' => PromotionGoal::FillEmptySlots->value,
            'status' => PromotionStatus::Active->value,
            'slots' => [],
        ]))->assertRedirect();
        $this->assertSame(PromotionStatus::Active, $promotion->refresh()->status);

        $this->actingAs($owner)->put(route('owner.promotions.update', $promotion), $this->campaignData($venue, [
            'resource_id' => $resource->getKey(),
            'promotion_type' => PromotionType::Deal->value,
            'goal' => PromotionGoal::FillEmptySlots->value,
            'status' => PromotionStatus::Completed->value,
            'slots' => [],
        ]))->assertRedirect();
        $this->assertSame(PromotionStatus::Completed, $promotion->refresh()->status);

        $this->actingAs($owner)->put(route('owner.promotions.update', $promotion), $this->campaignData($venue, [
            'resource_id' => $resource->getKey(),
            'promotion_type' => PromotionType::Deal->value,
            'goal' => PromotionGoal::FillEmptySlots->value,
            'status' => PromotionStatus::Active->value,
            'slots' => [],
        ]))->assertSessionHasErrors('status');
        $this->assertSame(PromotionStatus::Completed, $promotion->refresh()->status);
        $this->assertFalse($promotion->is_active);
    }

    public function test_empty_slot_finder_is_deterministic_tenant_safe_and_excludes_booked_or_promoted_time(): void
    {
        $now = CarbonImmutable::parse('2026-09-01 16:00', 'Asia/Manila');
        $this->travelTo($now);
        [$organization, $venue, $resource] = $this->setupInventory();
        [, , $otherResource] = $this->setupInventory(['slug' => 'another-owner-slots']);
        Booking::factory()->for($resource, 'resource')->create([
            'start_at' => CarbonImmutable::parse('2026-09-01 17:00', 'Asia/Manila')->utc(),
            'end_at' => CarbonImmutable::parse('2026-09-01 18:00', 'Asia/Manila')->utc(),
        ]);
        $promotion = Promotion::factory()->for($venue)->create([
            'organization_id' => $organization->getKey(),
            'resource_id' => $resource->getKey(),
            'starts_on' => '2026-09-01',
            'ends_on' => '2026-09-01',
            'targets_specific_slots' => true,
        ]);
        PromotionSlot::factory()->for($promotion)->create(
            $this->slotData($resource, '2026-09-01', '18:00', '19:00'),
        );

        $slots = app(EmptySlotFinder::class)->upcoming($organization, horizonDays: 1, limit: 40, at: $now);

        $this->assertNotEmpty($slots);
        $this->assertTrue($slots->every(fn (array $slot) => $slot['venue_id'] === $venue->getKey()));
        $this->assertFalse($slots->contains(fn (array $slot) => $slot['resource_id'] === $otherResource->getKey()));
        $this->assertFalse($slots->contains(fn (array $slot) => $slot['starts_at_time'] === '17:00'));
        $this->assertFalse($slots->contains(fn (array $slot) => $slot['starts_at_time'] === '18:00'));
        $this->assertTrue($slots->contains(fn (array $slot) => $slot['starts_at_time'] === '19:00'
            && $slot['is_last_minute']
            && $slot['reason'] === 'available_within_24_hours'));
    }

    public function test_specific_slot_campaign_is_visible_only_for_eligible_marketplace_context(): void
    {
        [$organization, $venue, $resource] = $this->setupInventory();
        $date = now('Asia/Manila')->addDay()->toDateString();
        $promotion = Promotion::factory()->for($venue)->create([
            'organization_id' => $organization->getKey(),
            'resource_id' => $resource->getKey(),
            'title' => 'Tomorrow evening recovery deal',
            'goal' => PromotionGoal::PromoteTodayOrTonight,
            'promotion_type' => PromotionType::SpecificSlots,
            'starts_on' => now('Asia/Manila')->toDateString(),
            'ends_on' => $date,
            'targets_specific_slots' => true,
        ]);
        $slot = PromotionSlot::factory()->for($promotion)->create(
            $this->slotData($resource, $date, '18:00', '19:00'),
        );

        $this->get(route('marketplace.deals'))
            ->assertOk()
            ->assertSee($promotion->title)
            ->assertSee('slot='.$slot->slot_token, false);
        $this->get(route('marketplace.venues.show', [
            'venueSlug' => $venue->slug,
            ...$promotion->marketplaceParameters(),
        ]))->assertOk();
        $click = AnalyticsEvent::query()
            ->where('event_type', AnalyticsEventType::PromotionClick)
            ->where('promotion_id', $promotion->getKey())
            ->firstOrFail();
        $this->assertSame($promotion->campaign_token, $click->metadata['campaign_token']);
        $this->assertSame($slot->slot_token, $click->metadata['placement_token']);
        $this->get(route('marketplace.courts.index', [
            'date' => $date,
            'start_time' => '18:00',
            'duration_minutes' => 60,
        ]))->assertOk()
            ->assertSee($promotion->title)
            ->assertSee('data-effective-hourly-price="520.00"', false);
        $this->get(route('marketplace.courts.index', [
            'date' => $date,
            'start_time' => '19:00',
            'duration_minutes' => 60,
        ]))->assertOk()
            ->assertDontSee($promotion->title)
            ->assertSee('data-effective-hourly-price="650.00"', false);
    }

    public function test_sport_audience_eligibility_uses_catalog_and_never_exposes_player_identity(): void
    {
        [$organization, $venue, $badmintonResource] = $this->setupInventory();
        $pickleball = Sport::query()->create([
            'name' => 'Pickleball',
            'slug' => 'pickleball',
            'is_active' => true,
        ]);
        $pickleballResource = CourtResource::factory()->for($venue)->for($pickleball)->create([
            'name' => 'Pickleball Court',
            'base_hourly_rate' => '650.00',
            'booking_increment_minutes' => 60,
        ]);
        $promotion = Promotion::factory()->for($venue)->create([
            'organization_id' => $organization->getKey(),
            'resource_id' => null,
            'audience_sport_id' => $badmintonResource->sport_id,
            'title' => 'Badminton audience campaign',
            'starts_on' => now('Asia/Manila')->toDateString(),
            'ends_on' => now('Asia/Manila')->addWeek()->toDateString(),
        ]);

        $this->get(route('marketplace.courts.index', ['sport' => 'badminton']))
            ->assertOk()
            ->assertSee($promotion->title);
        $this->get(route('marketplace.courts.index', ['sport' => 'pickleball']))
            ->assertOk()
            ->assertDontSee($promotion->title);

        $this->actingAs(User::factory()->create())->post(route('player.bookings.store', $venue->slug), [
            ...$this->holdData($pickleballResource, $this->futureDate(), '09:00'),
            'campaign' => $promotion->campaign_token,
        ])->assertSessionHasErrors('campaign');
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_promotion_never_bypasses_booking_conflict_protection(): void
    {
        [$organization, $venue, $resource] = $this->setupInventory();
        $date = $this->futureDate();
        $promotion = Promotion::factory()->for($venue)->create([
            'organization_id' => $organization->getKey(),
            'resource_id' => $resource->getKey(),
            'promotion_type' => PromotionType::SpecificSlots,
            'starts_on' => $date,
            'ends_on' => $date,
            'targets_specific_slots' => true,
        ]);
        PromotionSlot::factory()->for($promotion)->create(
            $this->slotData($resource, $date, '09:00', '10:00'),
        );
        Booking::factory()->for($resource, 'resource')->create([
            'status' => BookingStatus::Confirmed,
            'start_at' => CarbonImmutable::parse("{$date} 09:00", 'Asia/Manila')->utc(),
            'end_at' => CarbonImmutable::parse("{$date} 10:00", 'Asia/Manila')->utc(),
        ]);

        $this->actingAs(User::factory()->create())->post(route('player.bookings.store', $venue->slug), [
            ...$this->holdData($resource, $date, '09:00'),
            'campaign' => $promotion->campaign_token,
        ])->assertSessionHasErrors('start_time');
        $this->assertDatabaseCount('bookings', 1);
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
            'name' => 'Promotion V2 Courts',
            'slug' => 'promotion-v2-courts',
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

    /** @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function campaignData(Venue $venue, array $overrides = []): array
    {
        return [
            'venue_id' => $venue->getKey(),
            'resource_id' => null,
            'audience_sport_id' => null,
            'title' => 'Owner empty-slot campaign',
            'description' => 'A focused owner-approved campaign.',
            'promotion_type' => PromotionType::SpecificSlots->value,
            'goal' => PromotionGoal::PromoteSpecificSlots->value,
            'status' => PromotionStatus::Active->value,
            'discount_type' => PromotionDiscountType::Percentage->value,
            'discount_value' => '20.00',
            'starts_on' => now('Asia/Manila')->toDateString(),
            'ends_on' => now('Asia/Manila')->addDays(7)->toDateString(),
            'days_of_week' => [],
            'starts_at_time' => null,
            'ends_at_time' => null,
            'slots' => [],
            'is_public' => true,
            ...$overrides,
        ];
    }

    /** @return array<string, mixed> */
    private function slotData(
        CourtResource $resource,
        string $date,
        string $start,
        string $end,
    ): array {
        return [
            'resource_id' => $resource->getKey(),
            'slot_date' => $date,
            'starts_at_time' => $start,
            'ends_at_time' => $end,
        ];
    }

    /** @return array<string, mixed> */
    private function holdData(CourtResource $resource, string $date, string $start): array
    {
        return [
            'resource_id' => $resource->getKey(),
            'booking_date' => $date,
            'start_time' => $start,
            'duration_minutes' => 60,
            'customer_name' => 'Promotion V2 Player',
            'terms' => '1',
        ];
    }

    private function futureDate(): string
    {
        return now('Asia/Manila')->addDays(7)->toDateString();
    }
}
