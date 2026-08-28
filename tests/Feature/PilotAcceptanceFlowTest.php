<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Enums\PromotionDiscountType;
use App\Enums\PromotionType;
use App\Enums\ResourceSetting;
use App\Enums\ResourceType;
use App\Models\Amenity;
use App\Models\Booking;
use App\Models\CourtResource;
use App\Models\Organization;
use App\Models\Promotion;
use App\Models\Sport;
use App\Models\User;
use App\Models\Venue;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PilotAcceptanceFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_pilot_owner_player_payment_promotion_and_analytics_flow(): void
    {
        $this->post(route('register'), [
            'name' => 'Pilot Owner',
            'email' => 'pilot.owner@example.com',
            'organization_name' => 'Pilot Courts',
            'password' => 'pilot-password',
            'password_confirmation' => 'pilot-password',
        ])->assertRedirect(route('owner.dashboard'));

        $owner = User::query()->where('email', 'pilot.owner@example.com')->firstOrFail();
        $organization = Organization::query()->where('name', 'Pilot Courts')->firstOrFail();
        $sport = Sport::factory()->create(['name' => 'Badminton', 'slug' => 'badminton']);
        $amenity = Amenity::factory()->create(['name' => 'Parking', 'slug' => 'parking']);

        $this->post(route('owner.venues.store'), [
            ...$this->venueData($sport, $amenity),
            'is_published' => false,
        ])->assertRedirect();

        $venue = Venue::query()->where('slug', 'pilot-courts-makati')->firstOrFail();

        $this->post(route('owner.venues.resources.store', $venue), $this->resourceData($sport))
            ->assertRedirect(route('owner.venues.show', $venue));
        $resource = CourtResource::query()->where('venue_id', $venue->getKey())->firstOrFail();

        $this->put(route('owner.venues.update', $venue), [
            ...$this->venueData($sport, $amenity),
            'is_published' => true,
        ])->assertRedirect(route('owner.venues.show', $venue));

        $date = CarbonImmutable::now($organization->timezone)->addDays(2)->toDateString();
        $this->post(route('logout'))->assertRedirect(route('marketplace.home'));
        $this->get(route('marketplace.venues.show', [
            'venueSlug' => $venue->slug,
            'resource' => $resource->getKey(),
            'date' => $date,
            'duration' => 60,
        ]))->assertOk()->assertSee('Pilot Courts Makati');

        $this->post(route('player.register'), [
            'name' => 'Pilot Player',
            'email' => 'pilot.player@example.com',
            'password' => 'player-password',
            'password_confirmation' => 'player-password',
        ])->assertRedirect(route('player.bookings.index'));
        $player = User::query()->where('email', 'pilot.player@example.com')->firstOrFail();

        $this->post(route('player.bookings.store', $venue->slug), [
            ...$this->holdData($resource, $date, '10:00'),
            'organization_id' => 999999,
            'venue_id' => 999999,
            'total_amount' => '0.01',
        ])->assertRedirect();

        $firstBooking = Booking::query()->where('player_user_id', $player->getKey())->firstOrFail();
        $this->assertSame($organization->getKey(), $firstBooking->organization_id);
        $this->assertSame('1000.00', $firstBooking->total_amount);
        $this->assertSame(BookingStatus::Hold, $firstBooking->status);

        $this->post(route('player.bookings.confirm', $firstBooking->reference))->assertRedirect();
        $this->assertSame(BookingStatus::Confirmed, $firstBooking->refresh()->status);
        $this->assertSame(PaymentStatus::Pending, $firstBooking->payment_status);

        $this->actingAs($owner)->withSession(['tenant.organization_id' => $organization->getKey()]);
        $this->get(route('owner.bookings.index', ['date' => $date]))
            ->assertOk()
            ->assertSee($firstBooking->reference);
        $this->patch(route('owner.bookings.payment.update', $firstBooking), [
            'status' => PaymentStatus::Paid->value,
            'note' => 'Paid at venue during pilot acceptance.',
        ])->assertRedirect();
        $this->assertSame(PaymentStatus::Paid, $firstBooking->refresh()->payment_status);

        $this->post(route('owner.promotions.store'), [
            'venue_id' => $venue->getKey(),
            'resource_id' => $resource->getKey(),
            'title' => 'Pilot launch deal',
            'description' => 'Twenty percent off the pilot court.',
            'promotion_type' => PromotionType::Deal->value,
            'discount_type' => PromotionDiscountType::Percentage->value,
            'discount_value' => '20.00',
            'starts_on' => now($organization->timezone)->toDateString(),
            'ends_on' => now($organization->timezone)->addMonth()->toDateString(),
            'days_of_week' => [],
            'starts_at_time' => null,
            'ends_at_time' => null,
            'is_active' => true,
            'is_public' => true,
        ])->assertRedirect();
        $promotion = Promotion::query()->where('title', 'Pilot launch deal')->firstOrFail();

        $this->actingAs($player);
        $this->get(route('marketplace.venues.show', [
            'venueSlug' => $venue->slug,
            'resource' => $resource->getKey(),
            'date' => $date,
            'duration' => 60,
            'campaign' => $promotion->campaign_token,
        ]))->assertOk()->assertSee('Pilot launch deal');
        $this->post(route('player.bookings.store', $venue->slug), [
            ...$this->holdData($resource, $date, '11:00'),
            'campaign' => $promotion->campaign_token,
            'discount_value' => '99.99',
            'total_amount' => '0.01',
        ])->assertRedirect();

        $promotedBooking = Booking::query()
            ->where('player_user_id', $player->getKey())
            ->where('promotion_id', $promotion->getKey())
            ->firstOrFail();
        $this->assertSame('800.00', $promotedBooking->total_amount);
        $this->assertSame('200.00', $promotedBooking->discount_amount);
        $this->assertSame($promotion->campaign_token, $promotedBooking->promotion_campaign_token);
        $this->post(route('player.bookings.confirm', $promotedBooking->reference))->assertRedirect();

        $this->actingAs($owner)->withSession(['tenant.organization_id' => $organization->getKey()]);
        $this->get(route('owner.analytics'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('report.metrics.booking_starts', 2)
                ->where('report.metrics.completed_bookings', 2)
                ->where('report.metrics.booking_revenue', '1800.00')
                ->where('report.promotions.0.bookings', 1)
                ->where('report.promotions.0.revenue', '800.00'));
    }

    /** @return array<string, mixed> */
    private function venueData(Sport $sport, Amenity $amenity): array
    {
        return [
            'name' => 'Pilot Courts Makati',
            'slug' => 'pilot-courts-makati',
            'description' => 'A complete controlled pilot facility.',
            'address' => '10 Pilot Avenue',
            'city' => 'Makati',
            'province' => 'Metro Manila',
            'latitude' => null,
            'longitude' => null,
            'phone' => '+63 2 8000 1234',
            'email' => 'pilot.venue@example.com',
            'website' => null,
            'sports' => [$sport->getKey()],
            'amenities' => [$amenity->getKey()],
        ];
    }

    /** @return array<string, mixed> */
    private function resourceData(Sport $sport): array
    {
        return [
            'name' => 'Pilot Court 1',
            'sport_id' => $sport->getKey(),
            'resource_type' => ResourceType::Court->value,
            'setting' => ResourceSetting::Indoor->value,
            'is_active' => true,
            'base_hourly_rate' => '1000.00',
            'booking_increment_minutes' => 60,
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
            'customer_name' => 'Pilot Player',
            'customer_phone' => '+63 917 000 1234',
            'terms' => true,
        ];
    }
}
