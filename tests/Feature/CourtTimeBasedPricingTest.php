<?php

namespace Tests\Feature;

use App\Bookings\AvailabilityService;
use App\Bookings\BookingPrice;
use App\Bookings\CreateBooking;
use App\Enums\BookingStatus;
use App\Enums\PromotionDiscountType;
use App\Models\Booking;
use App\Models\CourtPricingRule;
use App\Models\CourtResource;
use App\Models\Membership;
use App\Models\OperatingHour;
use App\Models\Organization;
use App\Models\Promotion;
use App\Models\Sport;
use App\Models\User;
use App\Models\Venue;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourtTimeBasedPricingTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_manage_pricing_rules_only_for_their_own_court(): void
    {
        [$organization, $venue, $resource, $owner] = $this->setupCourt();
        [$otherOrganization, , , $otherOwner] = $this->setupCourt('Other Venue');

        $this->owner($owner, $organization)
            ->get(route('owner.venues.resources.pricing.index', [$venue, $resource]))
            ->assertOk();

        $this->owner($owner, $organization)
            ->post(route('owner.venues.resources.pricing.store', [$venue, $resource]), $this->ruleData())
            ->assertRedirect();

        $rule = CourtPricingRule::query()->sole();
        $this->assertSame('900.00', $rule->hourly_rate);

        $this->owner($owner, $organization)
            ->put(route('owner.venues.resources.pricing.update', [$venue, $resource, $rule]), [
                ...$this->ruleData(),
                'hourly_rate' => '950.00',
            ])->assertRedirect();
        $this->assertSame('950.00', $rule->refresh()->hourly_rate);

        $this->owner($otherOwner, $otherOrganization)
            ->delete(route('owner.venues.resources.pricing.destroy', [$venue, $resource, $rule]))
            ->assertForbidden();

        $this->owner($owner, $organization)
            ->delete(route('owner.venues.resources.pricing.destroy', [$venue, $resource, $rule]))
            ->assertRedirect();
        $this->assertDatabaseCount('court_pricing_rules', 0);
    }

    public function test_overlapping_pricing_rules_are_rejected_but_adjacent_rules_are_allowed(): void
    {
        [$organization, $venue, $resource, $owner] = $this->setupCourt();

        $this->owner($owner, $organization)
            ->post(route('owner.venues.resources.pricing.store', [$venue, $resource]), $this->ruleData())
            ->assertRedirect();

        $this->owner($owner, $organization)
            ->post(route('owner.venues.resources.pricing.store', [$venue, $resource]), [
                ...$this->ruleData(),
                'name' => 'Overlapping rate',
                'starts_at_time' => '20:00',
                'ends_at_time' => '23:00',
            ])->assertSessionHasErrors('schedule');

        $this->owner($owner, $organization)
            ->post(route('owner.venues.resources.pricing.store', [$venue, $resource]), [
                ...$this->ruleData(),
                'name' => 'Morning rate',
                'starts_at_time' => '08:00',
                'ends_at_time' => '17:00',
            ])->assertRedirect();

        $this->assertDatabaseCount('court_pricing_rules', 2);
    }

    public function test_booking_crossing_a_price_boundary_is_prorated_and_snapshotted(): void
    {
        [$organization, , $resource, $owner] = $this->setupCourt();
        $rule = $this->createRule($resource);

        $booking = $this->createBooking($organization, $owner, $resource, '16:30', '17:30');

        $this->assertSame('775.00', $booking->unit_price);
        $this->assertSame('775.00', $booking->total_amount);
        $this->assertCount(2, $booking->pricing_rule_snapshot);
        $this->assertSame('Regular price', $booking->pricing_rule_snapshot[0]['name']);
        $this->assertSame('Evening peak hours', $booking->pricing_rule_snapshot[1]['name']);

        $rule->update(['hourly_rate' => '1200.00']);

        $booking->refresh();
        $this->assertSame('775.00', $booking->total_amount);
        $this->assertSame('900.00', $booking->pricing_rule_snapshot[1]['hourly_rate']);
    }

    public function test_promotions_discount_the_scheduled_price_instead_of_the_fallback_rate(): void
    {
        [, $venue, $resource] = $this->setupCourt();
        $this->createRule($resource);
        $window = app(AvailabilityService::class)->window(
            $resource,
            $this->futureDate(),
            '17:00',
            '18:00',
        );
        $promotion = Promotion::factory()->for($venue)->create([
            'resource_id' => $resource->getKey(),
            'discount_type' => PromotionDiscountType::Percentage,
            'discount_value' => '20.00',
            'starts_on' => $this->futureDate(),
            'ends_on' => $this->futureDate(),
        ]);

        $price = app(BookingPrice::class)->quote($resource, 60, $promotion, $window);

        $this->assertSame('900.00', $price['original_unit_price']);
        $this->assertSame('720.00', $price['unit_price']);
        $this->assertSame('720.00', $price['total_amount']);
        $this->assertSame('180.00', $price['discount_amount']);
    }

    public function test_availability_exposes_the_actual_price_for_each_slot(): void
    {
        [$organization, , $resource, $owner] = $this->setupCourt();
        $this->createRule($resource);

        $this->owner($owner, $organization)
            ->getJson(route('owner.bookings.availability', [
                'resource_id' => $resource->getKey(),
                'date' => $this->futureDate(),
                'duration_minutes' => 30,
            ]))
            ->assertOk()
            ->assertJsonFragment([
                'start_time' => '17:00',
                'end_time' => '17:30',
                'unit_price' => '900.00',
                'total_amount' => '450.00',
                'has_time_based_price' => true,
            ])
            ->assertJsonFragment([
                'start_time' => '16:30',
                'end_time' => '17:00',
                'unit_price' => '650.00',
                'total_amount' => '325.00',
                'has_time_based_price' => false,
            ]);
    }

    public function test_public_marketplace_shows_the_scheduled_slot_price_and_lowest_rate(): void
    {
        [, $venue, $resource] = $this->setupCourt();
        $this->createRule($resource)->update(['hourly_rate' => '400.00']);

        $this->get(route('marketplace.venues.show', [
            'venueSlug' => $venue->slug,
            'resource' => $resource->getKey(),
            'date' => $this->futureDate(),
        ]))
            ->assertOk()
            ->assertSee('₱200.00')
            ->assertSee('Scheduled price');

        $this->get(route('marketplace.courts.index'))
            ->assertOk()
            ->assertSee('₱400');
    }

    /** @return array{Organization, Venue, CourtResource, User} */
    private function setupCourt(string $venueName = 'Time Price Courts'): array
    {
        $organization = Organization::factory()->create(['timezone' => 'Asia/Manila']);
        $owner = User::factory()->create();
        Membership::factory()->owner()->for($owner)->for($organization)->create();
        $venue = Venue::factory()->for($organization)->published()->verified()->create(['name' => $venueName]);
        $sport = Sport::factory()->create();
        $resource = CourtResource::factory()->for($venue)->for($sport)->create([
            'base_hourly_rate' => '650.00',
            'booking_increment_minutes' => 30,
        ]);
        OperatingHour::factory()->for($venue)->create([
            'day_of_week' => CarbonImmutable::parse($this->futureDate(), 'Asia/Manila')->dayOfWeek,
            'is_closed' => false,
            'opens_at' => '08:00',
            'closes_at' => '23:00',
        ]);

        return [$organization, $venue, $resource, $owner];
    }

    private function createRule(CourtResource $resource): CourtPricingRule
    {
        return $resource->pricingRules()->create([
            ...$this->ruleData(),
            'starts_at_time' => '17:00:00',
            'ends_at_time' => '22:00:00',
        ]);
    }

    /** @return array<string, mixed> */
    private function ruleData(): array
    {
        return [
            'name' => 'Evening peak hours',
            'days_of_week' => [CarbonImmutable::parse($this->futureDate(), 'Asia/Manila')->dayOfWeek],
            'starts_at_time' => '17:00',
            'ends_at_time' => '22:00',
            'hourly_rate' => '900.00',
        ];
    }

    private function createBooking(
        Organization $organization,
        User $owner,
        CourtResource $resource,
        string $start,
        string $end,
    ): Booking {
        return app(CreateBooking::class)->handle($organization->getKey(), $owner, [
            'resource_id' => $resource->getKey(),
            'booking_date' => $this->futureDate(),
            'start_time' => $start,
            'end_time' => $end,
            'status' => BookingStatus::Confirmed->value,
            'source' => 'manual',
            'hold_minutes' => null,
            'customer_name' => 'Pricing test player',
            'customer_email' => null,
            'customer_phone' => null,
            'notes' => null,
        ]);
    }

    private function futureDate(): string
    {
        return CarbonImmutable::now('Asia/Manila')->addDays(2)->toDateString();
    }

    private function owner(User $owner, Organization $organization): static
    {
        return $this->actingAs($owner)->withSession(['tenant.organization_id' => $organization->getKey()]);
    }
}
