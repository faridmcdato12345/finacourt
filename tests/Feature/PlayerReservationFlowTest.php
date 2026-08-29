<?php

namespace Tests\Feature;

use App\Bookings\CreateBooking;
use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\PaymentMode;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\CourtResource;
use App\Models\OperatingHour;
use App\Models\Organization;
use App\Models\Sport;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenuePhoto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class PlayerReservationFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_browse_availability_and_review_a_slot_without_an_account(): void
    {
        [, $venue, $resource] = $this->setupInventory();
        $coverPhoto = VenuePhoto::factory()->for($venue)->create([
            'storage_path' => 'venues/makati/reserve-cover.jpg',
            'alt_text' => 'Players on the featured court',
            'is_primary' => true,
        ]);
        $date = $this->futureDate();

        $this->get(route('marketplace.venues.show', [
            'venueSlug' => $venue->slug,
            'resource' => $resource->getKey(),
            'date' => $date,
            'duration' => 60,
        ]))
            ->assertOk()
            ->assertSee('Choose any consecutive available times within today’s opening hours.')
            ->assertSee('data-maximum-duration="1440"', false)
            ->assertSee('data-slot-picker', false)
            ->assertSee('data-start="09:00"', false);

        $this->get($this->reviewUrl($venue, $resource, $date))
            ->assertOk()
            ->assertSee('Review before we hold your court')
            ->assertSee('/storage/'.$coverPhoto->storage_path, false)
            ->assertSee('Players on the featured court')
            ->assertDontSee('Venue photo placeholder for '.$venue->name)
            ->assertSee('Sign in only when you’re ready')
            ->assertSee('₱650.00')
            ->assertDontSee('name="customer_name"', false);
    }

    public function test_guest_is_sent_to_player_login_only_when_creating_a_hold(): void
    {
        [, $venue, $resource] = $this->setupInventory();

        $this->post(route('player.bookings.store', $venue->slug), $this->holdData($resource))
            ->assertRedirect(route('player.login'));
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_player_can_register_and_login_during_the_booking_flow(): void
    {
        [, $venue, $resource] = $this->setupInventory();
        $return = $this->reviewUrl($venue, $resource, $this->futureDate(), false);

        $this->get(route('player.register', ['return' => $return]))->assertOk();
        $this->post(route('player.register'), [
            'name' => 'Alex Player',
            'email' => 'alex@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect(url($return));

        $player = User::query()->where('email', 'alex@example.com')->firstOrFail();
        $this->assertAuthenticatedAs($player);
        $this->assertDatabaseMissing('memberships', ['user_id' => $player->getKey()]);

        $this->post(route('logout'))->assertRedirect(route('marketplace.home'));
        $this->get(route('player.login', ['return' => $return]))->assertOk();
        $this->post(route('player.login'), [
            'email' => 'alex@example.com',
            'password' => 'password',
        ])->assertRedirect(url($return));
        $this->assertAuthenticatedAs($player);
    }

    public function test_valid_slot_creates_a_player_owned_hold_with_server_price_and_tenant(): void
    {
        [$organization, $venue, $resource] = $this->setupInventory();
        $player = User::factory()->create(['email' => 'player@example.com']);

        $this->actingAs($player)
            ->post(route('player.bookings.store', $venue->slug), [
                ...$this->holdData($resource),
                'organization_id' => 999999,
                'venue_id' => 999999,
                'price' => '1.00',
                'total_amount' => '1.00',
            ])->assertRedirect();

        $booking = Booking::query()->firstOrFail();
        $this->assertSame($organization->getKey(), $booking->organization_id);
        $this->assertSame($venue->getKey(), $booking->venue_id);
        $this->assertSame($resource->getKey(), $booking->resource_id);
        $this->assertSame($player->getKey(), $booking->player_user_id);
        $this->assertSame($player->getKey(), $booking->created_by_user_id);
        $this->assertSame(BookingStatus::Hold, $booking->status);
        $this->assertSame(BookingSource::Marketplace, $booking->source);
        $this->assertSame(PaymentMode::PayAtVenue, $booking->payment_mode);
        $this->assertSame(PaymentStatus::Pending, $booking->payment_status);
        $this->assertSame('650.00', $booking->unit_price);
        $this->assertSame('650.00', $booking->total_amount);
        $this->assertSame($player->email, $booking->customer_email);
        $this->assertNotNull($booking->expires_at);
    }

    public function test_consecutive_slots_create_one_multi_hour_hold_with_server_calculated_price(): void
    {
        [, $venue, $resource] = $this->setupInventory();
        $player = User::factory()->create(['email' => 'multi-slot-player@example.com']);

        $this->actingAs($player)
            ->post(route('player.bookings.store', $venue->slug), [
                ...$this->holdData($resource, '12:00'),
                'duration_minutes' => 180,
                'total_amount' => '1.00',
            ])->assertRedirect();

        $booking = Booking::query()->sole();

        $this->assertSame('12:00', $booking->start_at->setTimezone('Asia/Manila')->format('H:i'));
        $this->assertSame('15:00', $booking->end_at->setTimezone('Asia/Manila')->format('H:i'));
        $this->assertSame('1950.00', $booking->total_amount);
        $this->assertSame('650.00', $booking->unit_price);
    }

    public function test_player_can_book_more_than_four_consecutive_hours_within_operating_hours(): void
    {
        [, $venue, $resource] = $this->setupInventory();
        $player = User::factory()->create(['email' => 'long-booking-player@example.com']);
        $date = $this->futureDate();

        $this->get(route('player.bookings.create', [
            'venueSlug' => $venue->slug,
            'resource' => $resource->getKey(),
            'date' => $date,
            'start' => '12:00',
            'duration' => 360,
        ]))->assertOk();

        $this->actingAs($player)
            ->post(route('player.bookings.store', $venue->slug), [
                ...$this->holdData($resource, '12:00'),
                'duration_minutes' => 360,
            ])->assertRedirect();

        $booking = Booking::query()->sole();

        $this->assertSame('12:00', $booking->start_at->setTimezone('Asia/Manila')->format('H:i'));
        $this->assertSame('18:00', $booking->end_at->setTimezone('Asia/Manila')->format('H:i'));
        $this->assertSame('3900.00', $booking->total_amount);
    }

    public function test_stale_or_unavailable_slot_fails_cleanly(): void
    {
        [$organization, $venue, $resource, $owner] = $this->setupInventory();
        $this->createConfirmedBooking($organization, $resource, $owner);
        $player = User::factory()->create();

        $this->actingAs($player)
            ->from($this->reviewUrl($venue, $resource, $this->futureDate()))
            ->post(route('player.bookings.store', $venue->slug), $this->holdData($resource))
            ->assertRedirect($this->reviewUrl($venue, $resource, $this->futureDate()))
            ->assertSessionHasErrors('start_time');

        $this->assertDatabaseCount('bookings', 1);
    }

    public function test_expired_hold_cannot_be_confirmed_and_no_longer_blocks_the_slot(): void
    {
        [, $venue, $resource] = $this->setupInventory();
        $playerA = User::factory()->create();
        $hold = $this->createPlayerHold($playerA, $venue, $resource);
        $hold->update(['expires_at' => now()->subSecond()]);

        $this->actingAs($playerA)
            ->post(route('player.bookings.confirm', $hold->reference))
            ->assertSessionHasErrors('booking');

        $this->assertSame(BookingStatus::Expired, $hold->refresh()->effectiveStatus());

        $playerB = User::factory()->create();
        $this->actingAs($playerB)
            ->post(route('player.bookings.store', $venue->slug), $this->holdData($resource))
            ->assertRedirect();
        $this->assertDatabaseCount('bookings', 2);
    }

    public function test_player_can_confirm_a_hold_in_pay_at_venue_mode(): void
    {
        [, $venue, $resource] = $this->setupInventory();
        $player = User::factory()->create();
        $hold = $this->createPlayerHold($player, $venue, $resource);

        $this->actingAs($player)
            ->post(route('player.bookings.confirm', $hold->reference))
            ->assertRedirect(route('player.bookings.show', $hold->reference))
            ->assertSessionHas('status', 'Reservation confirmed. Payment is due at the venue.');

        $this->assertSame(BookingStatus::Confirmed, $hold->refresh()->status);
        $this->assertNull($hold->expires_at);
    }

    public function test_player_history_and_details_only_include_their_own_bookings(): void
    {
        [, $venue, $resource] = $this->setupInventory();
        $playerA = User::factory()->create();
        $playerB = User::factory()->create();
        $bookingA = $this->createPlayerHold($playerA, $venue, $resource);
        $bookingA->update(['status' => BookingStatus::Cancelled, 'cancelled_at' => now()]);
        $bookingB = $this->createPlayerHold($playerB, $venue, $resource, '11:00');

        $this->actingAs($playerA)
            ->get(route('player.bookings.index'))
            ->assertOk()
            ->assertSee($bookingA->reference)
            ->assertDontSee($bookingB->reference);

        $this->actingAs($playerA)
            ->get(route('player.bookings.show', $bookingB->reference))
            ->assertNotFound();
    }

    public function test_player_can_cancel_own_future_reservation_but_not_another_players(): void
    {
        [, $venue, $resource] = $this->setupInventory();
        $playerA = User::factory()->create();
        $playerB = User::factory()->create();
        $booking = $this->createPlayerHold($playerA, $venue, $resource);

        $this->actingAs($playerB)
            ->patch(route('player.bookings.cancel', $booking->reference))
            ->assertNotFound();
        $this->assertSame(BookingStatus::Hold, $booking->refresh()->status);

        $this->actingAs($playerA)
            ->patch(route('player.bookings.cancel', $booking->reference), [
                'cancellation_reason' => 'Plans changed',
            ])->assertRedirect(route('player.bookings.show', $booking->reference));

        $this->assertSame(BookingStatus::Cancelled, $booking->refresh()->status);
        $this->assertSame($playerA->getKey(), $booking->cancelled_by_user_id);

        $this->actingAs($playerB)
            ->post(route('player.bookings.store', $venue->slug), $this->holdData($resource))
            ->assertRedirect();
    }

    public function test_resource_from_another_venue_is_rejected_even_with_tampered_values(): void
    {
        [, $venueA] = $this->setupInventory(['slug' => 'venue-a']);
        [, , $resourceB] = $this->setupInventory(['slug' => 'venue-b', 'name' => 'Venue B']);
        $player = User::factory()->create();

        $this->actingAs($player)
            ->post(route('player.bookings.store', $venueA->slug), [
                ...$this->holdData($resourceB),
                'organization_id' => $resourceB->venue->organization_id,
                'total_amount' => '0.01',
            ])
            ->assertSessionHasErrors('resource_id');

        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_signed_share_link_is_non_sensitive_and_unsigned_link_is_rejected(): void
    {
        [, $venue, $resource] = $this->setupInventory();
        $player = User::factory()->create(['email' => 'private-player@example.com']);
        $booking = $this->createPlayerHold($player, $venue, $resource);
        $signedUrl = URL::signedRoute('bookings.share', $booking->reference);

        $this->get($signedUrl)
            ->assertOk()
            ->assertSee($booking->reference)
            ->assertSee($venue->name)
            ->assertDontSee('private-player@example.com')
            ->assertDontSee($booking->customer_name)
            ->assertSee('<meta name="robots" content="noindex,nofollow">', false);

        $this->get(route('bookings.share', $booking->reference))->assertForbidden();
    }

    /** @param array<string, mixed> $venueAttributes
     * @return array{Organization, Venue, CourtResource, User}
     */
    private function setupInventory(array $venueAttributes = []): array
    {
        $organization = Organization::factory()->create(['timezone' => 'Asia/Manila']);
        $owner = User::factory()->create();
        $venue = Venue::factory()->for($organization)->published()->create([
            'name' => 'Makati Players Club',
            'slug' => 'makati-players-club',
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
            'is_active' => true,
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
    private function holdData(CourtResource $resource, string $start = '09:00'): array
    {
        return [
            'resource_id' => $resource->getKey(),
            'booking_date' => $this->futureDate(),
            'start_time' => $start,
            'duration_minutes' => 60,
            'customer_name' => 'Jamie Player',
            'customer_phone' => '+63 900 000 0000',
            'terms' => '1',
        ];
    }

    private function createPlayerHold(
        User $player,
        Venue $venue,
        CourtResource $resource,
        string $start = '09:00',
    ): Booking {
        $this->actingAs($player)
            ->post(route('player.bookings.store', $venue->slug), $this->holdData($resource, $start))
            ->assertRedirect();

        return Booking::query()->where('player_user_id', $player->getKey())->latest('id')->firstOrFail();
    }

    private function createConfirmedBooking(
        Organization $organization,
        CourtResource $resource,
        User $owner,
    ): Booking {
        return app(CreateBooking::class)->handle($organization->getKey(), $owner, [
            'resource_id' => $resource->getKey(),
            'booking_date' => $this->futureDate(),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'status' => BookingStatus::Confirmed->value,
            'source' => BookingSource::Manual->value,
            'customer_name' => 'Existing Customer',
        ]);
    }

    private function reviewUrl(
        Venue $venue,
        CourtResource $resource,
        string $date,
        bool $absolute = true,
    ): string {
        return route('player.bookings.create', [
            'venueSlug' => $venue->slug,
            'resource' => $resource->getKey(),
            'date' => $date,
            'start' => '09:00',
            'duration' => 60,
        ], $absolute);
    }

    private function futureDate(): string
    {
        return now('Asia/Manila')->addDays(7)->toDateString();
    }
}
