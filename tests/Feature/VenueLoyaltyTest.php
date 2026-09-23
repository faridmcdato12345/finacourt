<?php

namespace Tests\Feature;

use App\Bookings\CreateBooking;
use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\OrganizationPermission;
use App\Enums\PaymentMode;
use App\Enums\PaymentStatus;
use App\Enums\RefundRequestStatus;
use App\Loyalty\VenueLoyalty;
use App\Models\Booking;
use App\Models\CourtResource;
use App\Models\Membership;
use App\Models\OperatingHour;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Promotion;
use App\Models\RefundRequest;
use App\Models\Sport;
use App\Models\User;
use App\Models\Venue;
use App\Payments\ApplyPaymentTransition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class VenueLoyaltyTest extends TestCase
{
    use RefreshDatabase;

    public function test_loyalty_has_an_owner_only_page_scoped_to_the_current_organization(): void
    {
        [$organization, $venue, , $owner] = $this->inventory();
        $staff = User::factory()->create();
        Membership::factory()->for($staff)->for($organization)
            ->withPermissions([OrganizationPermission::ManageInventory])->create();
        $otherVenue = Venue::factory()->for(Organization::factory())->create();

        $this->actingAs($staff)->get(route('owner.loyalty.index'))->assertForbidden();
        $this->actingAs($owner)->get(route('owner.loyalty.index', ['venue' => $venue->getKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Owner/Loyalty/Index')
                ->where('selectedVenue.id', $venue->getKey())
                ->where('selectedVenue.name', $venue->name)
                ->where('selectedVenue.active', false)
                ->where('selectedVenue.insights.repeat_players', 0)
                ->where('selectedVenue.insights.rewards_ready', 0)
                ->where('selectedVenue.insights.rewards_redeemed', 0)
                ->where('selectedVenue.insights.players_one_away', 0)
                ->where('selectedVenue.insights.discount_amount', '0.00')
                ->where('abilities.manage_loyalty', true)
                ->has('venues', 1));
        $this->get(route('owner.loyalty.index', ['venue' => $otherVenue->getKey()]))
            ->assertNotFound();
    }

    public function test_owner_loyalty_insights_count_paid_returns_and_rewards_without_refunded_games(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 23)->setTime(12, 0));
        [, $venue, $resource, $owner] = $this->inventory([
            'loyalty_active' => true,
            'loyalty_stamps_required' => 2,
        ]);
        $redeemer = User::factory()->create();
        $readyPlayer = User::factory()->create();
        $oneAwayPlayer = User::factory()->create();
        $refundingPlayer = User::factory()->create();

        $this->pastPaidGame($resource, $redeemer, 40);
        $this->pastPaidGame($resource, $redeemer, 10);
        $this->pastPaidGame($resource, $readyPlayer, 18);
        $this->pastPaidGame($resource, $readyPlayer, 7);
        $this->pastPaidGame($resource, $oneAwayPlayer, 4);
        $this->pastPaidGame($resource, $refundingPlayer, 9);
        $refundingGame = $this->pastPaidGame($resource, $refundingPlayer, 2);
        $this->artisan('loyalty:sync-stamps')->assertSuccessful();

        $refundingPayment = Payment::factory()->for($refundingGame)->create([
            'mode' => PaymentMode::HostedCheckout,
            'status' => PaymentStatus::Paid,
            'paid_at' => now()->subDays(3),
        ]);
        $this->refundRequestFor($refundingGame, $refundingPayment, RefundRequestStatus::Requested);

        $rewardBooking = Booking::factory()->for($resource, 'resource')->create([
            'player_user_id' => $redeemer->getKey(),
            'created_by_user_id' => $redeemer->getKey(),
            'status' => BookingStatus::Confirmed,
            'payment_mode' => PaymentMode::HostedCheckout,
            'payment_status' => PaymentStatus::Paid,
            'start_at' => now()->addDays(5),
            'end_at' => now()->addDays(5)->addHour(),
        ]);
        DB::table('loyalty_redemptions')->insert([
            'venue_id' => $venue->getKey(),
            'player_user_id' => $redeemer->getKey(),
            'booking_id' => $rewardBooking->getKey(),
            'terms_version' => $venue->fresh()->loyalty_terms_version,
            'court_discount_amount' => '65.00',
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
        ]);
        Payment::factory()->for($rewardBooking)->create([
            'mode' => PaymentMode::HostedCheckout,
            'status' => PaymentStatus::Paid,
            'paid_at' => now()->subDays(2),
        ]);

        $this->actingAs($owner)->get(route('owner.loyalty.index', ['venue' => $venue->getKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('selectedVenue.insights.repeat_players', 2)
                ->where('selectedVenue.insights.rewards_ready', 1)
                ->where('selectedVenue.insights.rewards_redeemed', 1)
                ->where('selectedVenue.insights.players_one_away', 2)
                ->where('selectedVenue.insights.discount_amount', '65.00'));

        $rewardPayment = $rewardBooking->payments()->firstOrFail();
        $this->refundRequestFor($rewardBooking, $rewardPayment, RefundRequestStatus::Processing);
        $this->get(route('owner.loyalty.index', ['venue' => $venue->getKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('selectedVenue.insights.rewards_ready', 1)
                ->where('selectedVenue.insights.rewards_redeemed', 0)
                ->where('selectedVenue.insights.discount_amount', '0.00'));
    }

    public function test_owner_must_opt_in_and_staff_cannot_change_the_venue_program(): void
    {
        [$organization, $venue, , $owner] = $this->inventory();
        $staff = User::factory()->create();
        Membership::factory()->for($staff)->for($organization)
            ->withPermissions([OrganizationPermission::ManageInventory])->create();

        $this->assertFalse($venue->fresh()->loyalty_active);
        $this->actingAs($staff)->patch(route('owner.venues.loyalty.update', $venue), ['active' => true])
            ->assertForbidden();
        $this->patch(route('owner.venues.loyalty.terms', $venue), [
            'stamps_required' => 8, 'discount_percent' => '15.00', 'discount_cap' => '200.00',
        ])->assertForbidden();
        $this->assertFalse($venue->fresh()->loyalty_active);

        $this->actingAs($owner)->patch(route('owner.venues.loyalty.terms', $venue), [
            'stamps_required' => 8, 'discount_percent' => '15.00', 'discount_cap' => '200.00',
        ])->assertRedirect();
        $this->assertSame(8, $venue->fresh()->loyalty_stamps_required);
        $this->assertSame('15.00', $venue->fresh()->loyalty_discount_percent);
        $this->assertSame('200.00', $venue->fresh()->loyalty_discount_cap);
        $this->assertSame(2, $venue->fresh()->loyalty_terms_version);
        $this->patch(route('owner.venues.loyalty.terms', $venue), [
            'stamps_required' => 0, 'discount_percent' => '150.00', 'discount_cap' => '20000.00',
        ])->assertSessionHasErrors(['stamps_required', 'discount_percent', 'discount_cap']);
        $this->assertSame(2, $venue->fresh()->loyalty_terms_version);
        $this->patch(route('owner.venues.loyalty.terms', $venue), [
            'stamps_required' => 8, 'discount_percent' => '15.00', 'discount_cap' => '200.00',
        ])->assertRedirect();
        $this->assertSame(2, $venue->fresh()->loyalty_terms_version);

        $this->actingAs($owner)->patch(route('owner.venues.loyalty.update', $venue), ['active' => true])
            ->assertRedirect();
        $this->assertTrue($venue->fresh()->loyalty_active);

        $this->patch(route('owner.venues.loyalty.update', $venue), ['active' => false])->assertRedirect();
        $this->assertFalse($venue->fresh()->loyalty_active);
    }

    public function test_public_discovery_badge_and_venue_details_show_only_for_active_loyalty(): void
    {
        [, $venue] = $this->inventory([
            'loyalty_active' => true,
            'loyalty_stamps_required' => 7,
            'loyalty_discount_percent' => '12.50',
            'loyalty_discount_cap' => '175.00',
        ]);

        $this->get(route('marketplace.courts.index'))->assertOk()
            ->assertSee('Loyalty rewards');
        $this->get(route('marketplace.venues.show', [
            'venueSlug' => $venue->slug,
            'campaign' => 'DEMO-FILL-SLOW-HOURS-15',
        ]))->assertOk()
            ->assertSeeInOrder(['data-venue-loyalty', 'Courts and pricing'])
            ->assertSee('7 stamps')
            ->assertSee('12.50% off')
            ->assertSee('capped at ₱175.00')
            ->assertSee('after it ends and payment is verified')
            ->assertSee('can stack with an eligible deal');

        $venue->update(['loyalty_active' => false]);

        $this->get(route('marketplace.courts.index'))->assertOk()
            ->assertDontSee('Loyalty rewards');
        $this->get(route('marketplace.venues.show', $venue->slug))->assertOk()
            ->assertSeeInOrder(['Loyalty rewards are not active', 'Courts and pricing'])
            ->assertDontSee('7 stamps')
            ->assertDontSee('12.50% off');
    }

    public function test_five_paid_games_earn_one_reward_once_and_refund_reverses_a_stamp(): void
    {
        [, $venue, $resource] = $this->inventory(['loyalty_active' => true]);
        $player = User::factory()->create();
        $bookings = [];

        foreach (range(1, 5) as $daysAgo) {
            $bookings[] = $this->pastPaidGame($resource, $player, $daysAgo);
        }
        $this->pastPaidGame($resource, $player, 1, 2);

        $this->artisan('loyalty:sync-stamps')->assertSuccessful();
        $this->artisan('loyalty:sync-stamps')->assertSuccessful();

        $loyalty = app(VenueLoyalty::class);
        $this->assertSame(5, $loyalty->balance($venue, $player)['stamps']);
        $this->assertSame(1, $loyalty->balance($venue, $player)['rewards_available']);
        $this->assertDatabaseCount('loyalty_stamps', 5);

        $payment = Payment::factory()->for($bookings[0])->create([
            'provider' => 'paymongo',
            'mode' => PaymentMode::HostedCheckout,
            'status' => PaymentStatus::Paid,
            'paid_at' => now()->subDays(6),
        ]);
        DB::transaction(fn () => app(ApplyPaymentTransition::class)->handleLocked(
            $payment, $bookings[0], PaymentStatus::Refunded, 'test_refund',
        ));
        $this->assertSame(4, $loyalty->balance($venue, $player)['stamps']);
        $this->assertSame(0, $loyalty->balance($venue, $player)['rewards_available']);
    }

    public function test_booking_page_shows_earned_stamps_without_counting_a_new_reservation_early(): void
    {
        [$organization, $venue, $resource] = $this->inventory([
            'loyalty_active' => true,
            'loyalty_stamps_required' => 1,
        ]);
        $player = User::factory()->create();
        $this->enableOnlinePayments();
        $bookingDate = now('Asia/Manila')->addWeek()->startOfWeek()->toDateString();
        $booking = app(CreateBooking::class)->handle($organization->getKey(), $player, [
            'resource_id' => $resource->getKey(),
            'booking_date' => $bookingDate,
            'start_time' => '09:00',
            'end_time' => '10:00',
            'status' => BookingStatus::Hold->value,
            'source' => BookingSource::Marketplace->value,
            'customer_name' => $player->name,
            'create_payment' => true,
            'payment_provider' => 'paymongo',
        ], $player);

        $this->actingAs($player)->get(route('player.bookings.show', $booking->reference))->assertOk()
            ->assertSee('You have <strong>0 earned stamps</strong>', false)
            ->assertSee('<strong>1 more qualifying game</strong> needed', false)
            ->assertSee('This reservation has not earned a stamp yet.');

        $playedAt = now('Asia/Manila')->subDay()->setTime(9, 0)->utc();
        $booking->update([
            'status' => BookingStatus::Confirmed,
            'payment_status' => PaymentStatus::Paid,
            'expires_at' => null,
            'start_at' => $playedAt,
            'end_at' => $playedAt->copy()->addHour(),
        ]);
        $booking->payment->update(['status' => PaymentStatus::Paid, 'paid_at' => now()->subDays(2)]);
        $this->get(route('player.bookings.show', $booking->reference))->assertOk()
            ->assertSee('data-post-game-message', false)
            ->assertSee('Thanks for playing!')
            ->assertSee('Your stamp is being checked')
            ->assertDontSee('You’re ready to play');

        $this->artisan('loyalty:sync-stamps')->assertSuccessful();
        $this->get(route('player.bookings.show', $booking->reference))->assertOk()
            ->assertSee('You have <strong>1 earned stamp</strong>', false)
            ->assertSee('<strong>1 reward ready</strong>', false)
            ->assertSee('This completed booking added one of the earned stamps shown above.')
            ->assertSee('Reward unlocked')
            ->assertSee('1 stamp =')
            ->assertSee('You earned a loyalty stamp from this game.')
            ->assertDontSee('You’re ready to play');

        $this->refundRequestFor($booking, $booking->payment, RefundRequestStatus::Requested);
        $this->get(route('player.bookings.show', $booking->reference))->assertOk()
            ->assertSee('You have <strong>0 earned stamps</strong>', false)
            ->assertSee('stamp is on hold while its refund is being reviewed')
            ->assertDontSee('<strong>1 reward ready</strong>', false);
        $this->assertSame(0, app(VenueLoyalty::class)->balance($venue, $player)['rewards_available']);

        DB::transaction(fn () => app(ApplyPaymentTransition::class)->handleLocked(
            $booking->payment->fresh(), $booking->fresh(), PaymentStatus::Refunded, 'test_refund',
        ));
        $this->get(route('player.bookings.show', $booking->reference))->assertOk()
            ->assertSee('This booking was refunded, so it cannot earn or keep a loyalty stamp.');
        $this->assertSame(0, app(VenueLoyalty::class)->balance($venue, $player)['stamps']);
    }

    public function test_player_bookings_page_shows_stamp_progress_and_celebrates_ready_rewards(): void
    {
        [, $venue, $resource] = $this->inventory([
            'loyalty_active' => true,
            'loyalty_stamps_required' => 3,
        ]);
        $player = User::factory()->create();

        $this->pastPaidGame($resource, $player, 3);
        $this->artisan('loyalty:sync-stamps')->assertSuccessful();
        $this->actingAs($player)->get(route('player.bookings.index'))->assertOk()
            ->assertSee('data-loyalty-card', false)
            ->assertSee('data-loyalty-carousel', false)
            ->assertSee('gap-4 overflow-x-auto', false)
            ->assertSee('data-loyalty-illustration class="flex h-40', false)
            ->assertSee('Play more. Get more.')
            ->assertSee('Next reward: 1/3 stamps')
            ->assertSee('2 games to go')
            ->assertSee('Stacks with deals')
            ->assertSee('Find a game to earn stamps')
            ->assertSee('How rewards work')
            ->assertDontSee('Swipe for more venues')
            ->assertDontSee('data-loyalty-direction="next"', false);

        $this->pastPaidGame($resource, $player, 2);
        $this->pastPaidGame($resource, $player, 1);
        $this->artisan('loyalty:sync-stamps')->assertSuccessful();
        $this->get(route('player.bookings.index'))->assertOk()
            ->assertSee('Reward unlocked!')
            ->assertSee('1 reward ready')
            ->assertSee('Next reward: 0/3 stamps')
            ->assertSee('Find a game to use a reward');

        $venue->update(['loyalty_active' => false]);
        $this->get(route('player.bookings.index'))->assertOk()
            ->assertSee('Earning paused')
            ->assertSee('1 reward ready')
            ->assertSee('rewards you already earned remain usable');

        $otherVenue = Venue::factory()->for($venue->organization)->published()->create([
            'name' => 'Another Loyalty Court',
            'slug' => 'another-loyalty-court',
            'loyalty_active' => true,
        ]);
        $otherResource = CourtResource::factory()->for($otherVenue)->for($resource->sport)->create();
        $this->pastPaidGame($otherResource, $player, 1);
        $this->artisan('loyalty:sync-stamps')->assertSuccessful();
        $response = $this->get(route('player.bookings.index'))->assertOk()
            ->assertSee('Another Loyalty Court')
            ->assertSee('Swipe for more venues')
            ->assertSee('w-[calc(100%-2.5rem)]', false)
            ->assertSee('data-loyalty-direction="previous"', false)
            ->assertSee('data-loyalty-direction="next"', false);
        $this->assertSame(2, substr_count($response->getContent(), 'data-loyalty-card'));
    }

    public function test_pending_refund_delays_stamp_until_declined(): void
    {
        [, $venue, $resource] = $this->inventory(['loyalty_active' => true]);
        $player = User::factory()->create();
        $booking = $this->pastPaidGame($resource, $player, 1);
        $payment = Payment::factory()->for($booking)->create([
            'provider' => 'paymongo',
            'mode' => PaymentMode::HostedCheckout,
            'status' => PaymentStatus::Paid,
            'paid_at' => now()->subDays(2),
        ]);
        $refund = $this->refundRequestFor($booking, $payment, RefundRequestStatus::Requested);

        $this->artisan('loyalty:sync-stamps')->assertSuccessful();
        $this->assertDatabaseCount('loyalty_stamps', 0);
        $this->assertNull($booking->fresh()->loyalty_processed_at);
        $this->assertSame(5, app(VenueLoyalty::class)->balance($venue, $player)['stamps_needed']);

        $refund->update(['status' => RefundRequestStatus::Rejected]);
        $this->artisan('loyalty:sync-stamps')->assertSuccessful();
        $this->assertSame(1, app(VenueLoyalty::class)->balance($venue, $player)['stamps']);
        $this->assertSame(4, app(VenueLoyalty::class)->balance($venue, $player)['stamps_needed']);
    }

    public function test_refunded_stamp_debt_blocks_new_rewards_even_after_owner_changes_terms(): void
    {
        [$organization, $venue, $resource, $owner] = $this->inventory([
            'loyalty_active' => true,
            'loyalty_stamps_required' => 1,
        ]);
        $player = User::factory()->create();
        $earningBooking = $this->pastPaidGame($resource, $player, 1);
        $this->artisan('loyalty:sync-stamps')->assertSuccessful();
        $this->enableOnlinePayments();

        $rewardBooking = app(CreateBooking::class)->handle($organization->getKey(), $player, [
            'resource_id' => $resource->getKey(),
            'booking_date' => now('Asia/Manila')->addWeek()->startOfWeek()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'status' => BookingStatus::Hold->value,
            'source' => BookingSource::Marketplace->value,
            'customer_name' => $player->name,
            'create_payment' => true,
            'payment_provider' => 'paymongo',
            'loyalty_reward_version' => 1,
        ], $player);
        $rewardBooking->update([
            'status' => BookingStatus::Confirmed,
            'payment_status' => PaymentStatus::Paid,
            'expires_at' => null,
        ]);
        $this->assertSame(0, app(VenueLoyalty::class)->balance($venue, $player)['rewards_available']);

        $payment = Payment::factory()->for($earningBooking)->create([
            'provider' => 'paymongo',
            'mode' => PaymentMode::HostedCheckout,
            'status' => PaymentStatus::Paid,
            'paid_at' => now()->subDays(2),
        ]);
        DB::transaction(fn () => app(ApplyPaymentTransition::class)->handleLocked(
            $payment, $earningBooking, PaymentStatus::Refunded, 'test_refund',
        ));
        $balance = app(VenueLoyalty::class)->balance($venue, $player);
        $this->assertSame(0, $balance['stamps']);
        $this->assertSame(1, $balance['reward_debt']);
        $this->assertSame(2, $balance['stamps_needed']);
        $this->actingAs($player)->get(route('player.bookings.index'))->assertOk()
            ->assertSee('A refunded game removed a stamp. 2 more qualifying games needed')
            ->assertDontSee('Next reward:');

        $this->actingAs($owner)->patch(route('owner.venues.loyalty.terms', $venue), [
            'stamps_required' => 2, 'discount_percent' => '15.00', 'discount_cap' => '200.00',
        ])->assertRedirect();
        $venue->refresh();
        $this->pastPaidGame($resource, $player, 2);
        $this->pastPaidGame($resource, $player, 3);
        $this->artisan('loyalty:sync-stamps')->assertSuccessful();
        $balance = app(VenueLoyalty::class)->balance($venue, $player);
        $this->assertSame(2, $balance['stamps']);
        $this->assertSame(0, $balance['rewards_available']);
        $this->assertSame(2, $balance['stamps_needed']);

        $this->pastPaidGame($resource, $player, 4);
        $this->pastPaidGame($resource, $player, 5);
        $this->artisan('loyalty:sync-stamps')->assertSuccessful();
        $this->assertSame(1, app(VenueLoyalty::class)->balance($venue, $player)['rewards_available']);
    }

    public function test_reward_is_reserved_by_a_hold_and_released_when_hold_expires_even_after_owner_pauses(): void
    {
        [$organization, $venue, $resource] = $this->inventory(['loyalty_active' => true]);
        $player = User::factory()->create();
        foreach (range(1, 5) as $daysAgo) {
            $this->pastPaidGame($resource, $player, $daysAgo);
        }
        $this->artisan('loyalty:sync-stamps')->assertSuccessful();
        $venue->update(['loyalty_active' => false]);
        $this->enableOnlinePayments();
        $bookingDate = now('Asia/Manila')->addWeek()->startOfWeek()->toDateString();

        $this->actingAs($player)->get(route('player.bookings.create', [
            'venueSlug' => $venue->slug,
            'resource' => $resource->getKey(),
            'date' => $bookingDate,
            'start' => '09:00',
            'duration' => 60,
        ]))->assertOk()
            ->assertSee('Loyalty reward ready')
            ->assertSee('data-online-total="₱650.00"', false)
            ->assertSee('data-loyalty-total="₱585.00"', false)
            ->assertSee('name="loyalty_reward_version" type="checkbox" value="1"', false);

        $booking = app(CreateBooking::class)->handle($organization->getKey(), $player, [
            'resource_id' => $resource->getKey(),
            'booking_date' => $bookingDate,
            'start_time' => '09:00',
            'end_time' => '10:00',
            'status' => BookingStatus::Hold->value,
            'source' => BookingSource::Marketplace->value,
            'customer_name' => $player->name,
            'customer_email' => $player->email,
            'create_payment' => true,
            'payment_provider' => 'paymongo',
            'loyalty_reward_version' => 1,
        ], $player);

        $this->assertSame('585.00', $booking->total_amount);
        $this->assertSame('65.00', $booking->discount_amount);
        $this->assertFalse($booking->loyalty_eligible);
        $this->assertSame('65.00', DB::table('loyalty_redemptions')->where('booking_id', $booking->getKey())->value('court_discount_amount'));
        $this->assertSame(0, app(VenueLoyalty::class)->balance($venue, $player)['rewards_available']);

        $booking->update(['expires_at' => now()->subMinute()]);
        $this->assertSame(1, app(VenueLoyalty::class)->balance($venue, $player)['rewards_available']);
        $booking->update(['payment_status' => PaymentStatus::Paid]);
        $this->assertSame(0, app(VenueLoyalty::class)->balance($venue, $player)['rewards_available']);
        $booking->update(['payment_status' => PaymentStatus::Refunded]);
        $this->assertSame(1, app(VenueLoyalty::class)->balance($venue, $player)['rewards_available']);
    }

    public function test_browser_cannot_claim_a_reward_without_five_stamps(): void
    {
        [$organization, , $resource] = $this->inventory(['loyalty_active' => true]);
        $player = User::factory()->create();
        $this->enableOnlinePayments();

        try {
            app(CreateBooking::class)->handle($organization->getKey(), $player, [
                'resource_id' => $resource->getKey(),
                'booking_date' => now('Asia/Manila')->addWeek()->startOfWeek()->toDateString(),
                'start_time' => '09:00',
                'end_time' => '10:00',
                'status' => BookingStatus::Hold->value,
                'source' => BookingSource::Marketplace->value,
                'customer_name' => $player->name,
                'create_payment' => true,
                'payment_provider' => 'paymongo',
                'loyalty_reward_version' => 1,
            ], $player);
            $this->fail('The reward should have been rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('loyalty', $exception->errors());
        }

        $this->assertDatabaseCount('bookings', 0);
        $this->assertDatabaseCount('loyalty_redemptions', 0);
    }

    public function test_reward_cannot_be_spent_when_it_would_leave_zero_payable_online_total(): void
    {
        [$organization, $venue, $resource] = $this->inventory([
            'loyalty_active' => true,
            'loyalty_stamps_required' => 1,
            'loyalty_discount_percent' => '100.00',
            'loyalty_discount_cap' => '1000.00',
        ]);
        $player = User::factory()->create();
        $this->pastPaidGame($resource, $player, 1);
        $this->artisan('loyalty:sync-stamps')->assertSuccessful();
        $this->enableOnlinePayments();
        $bookingDate = now('Asia/Manila')->addWeek()->startOfWeek()->toDateString();

        $this->actingAs($player)->get(route('player.bookings.create', [
            'venueSlug' => $venue->slug,
            'resource' => $resource->getKey(),
            'date' => $bookingDate,
            'start' => '09:00',
            'duration' => 60,
        ]))->assertOk()->assertDontSee('name="loyalty_reward_version" type="checkbox"', false);

        try {
            app(CreateBooking::class)->handle($organization->getKey(), $player, [
                'resource_id' => $resource->getKey(),
                'booking_date' => $bookingDate,
                'start_time' => '09:00',
                'end_time' => '10:00',
                'status' => BookingStatus::Hold->value,
                'source' => BookingSource::Marketplace->value,
                'customer_name' => $player->name,
                'create_payment' => true,
                'payment_provider' => 'paymongo',
                'loyalty_reward_version' => 1,
            ], $player);
            $this->fail('A zero-payable checkout must not consume a reward.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('loyalty', $exception->errors());
        }
        $this->assertDatabaseCount('loyalty_redemptions', 0);
    }

    public function test_player_chooses_when_to_redeem_and_stacks_with_a_public_deal(): void
    {
        [$organization, $venue, $resource] = $this->inventory(['loyalty_active' => true]);
        $player = User::factory()->create();
        foreach (range(1, 5) as $daysAgo) {
            $this->pastPaidGame($resource, $player, $daysAgo);
        }
        $this->artisan('loyalty:sync-stamps')->assertSuccessful();
        $this->enableOnlinePayments();

        $smallDeal = Promotion::factory()->for($venue)->create([
            'organization_id' => $organization->getKey(),
            'resource_id' => $resource->getKey(),
            'discount_value' => '5.00',
        ]);
        $monday = now('Asia/Manila')->addWeek()->startOfWeek()->toDateString();
        $tuesday = now('Asia/Manila')->addWeek()->startOfWeek()->addDay()->toDateString();
        $wednesday = now('Asia/Manila')->addWeek()->startOfWeek()->addDays(2)->toDateString();
        $thursday = now('Asia/Manila')->addWeek()->startOfWeek()->addDays(3)->toDateString();

        $this->actingAs($player)->get(route('player.bookings.create', [
            'venueSlug' => $venue->slug,
            'resource' => $resource->getKey(),
            'date' => $monday,
            'start' => '09:00',
            'duration' => 60,
        ]))->assertOk()
            ->assertSee('Loyalty reward ready')
            ->assertSee('data-online-total="₱617.50"', false)
            ->assertSee('data-loyalty-total="₱555.75"', false)
            ->assertSee('your deal and loyalty reward both apply')
            ->assertSee('data-pay-at-venue-total="₱617.50"', false);

        $this->post(route('player.bookings.store', $venue->slug), [
            'resource_id' => $resource->getKey(),
            'booking_date' => $monday,
            'start_time' => '09:00',
            'duration_minutes' => 60,
            'payment_option' => 'pay_at_venue',
            'loyalty_reward_version' => 1,
            'customer_name' => $player->name,
            'terms' => true,
        ])->assertRedirect();
        $payAtVenueBooking = Booking::query()->latest('id')->firstOrFail();
        $this->assertSame($smallDeal->getKey(), $payAtVenueBooking->promotion_id);
        $this->assertSame('617.50', $payAtVenueBooking->total_amount);
        $this->assertDatabaseCount('loyalty_redemptions', 0);

        $this->post(route('player.bookings.store', $venue->slug), [
            'resource_id' => $resource->getKey(),
            'booking_date' => $thursday,
            'start_time' => '09:00',
            'duration_minutes' => 60,
            'payment_option' => 'online',
            'customer_name' => $player->name,
            'terms' => true,
        ])->assertRedirect();
        $savedRewardBooking = Booking::query()->latest('id')->firstOrFail();
        $this->assertSame($smallDeal->getKey(), $savedRewardBooking->promotion_id);
        $this->assertDatabaseCount('loyalty_redemptions', 0);
        $this->assertSame(1, app(VenueLoyalty::class)->balance($venue, $player)['rewards_available']);

        $this->post(route('player.bookings.store', $venue->slug), [
            'resource_id' => $resource->getKey(),
            'booking_date' => $tuesday,
            'start_time' => '09:00',
            'duration_minutes' => 60,
            'payment_option' => 'online',
            'loyalty_reward_version' => 1,
            'customer_name' => $player->name,
            'terms' => true,
        ])->assertRedirect();
        $onlineBooking = Booking::query()->latest('id')->firstOrFail();
        $this->assertSame($smallDeal->getKey(), $onlineBooking->promotion_id);
        $this->assertSame('555.75', $onlineBooking->total_amount);
        $this->assertSame('94.25', $onlineBooking->discount_amount);
        $this->assertDatabaseHas('loyalty_redemptions', [
            'booking_id' => $onlineBooking->getKey(), 'court_discount_amount' => '61.75',
        ]);
        $onlineBooking->update(['expires_at' => now()->subMinute()]);
        $this->assertSame(1, app(VenueLoyalty::class)->balance($venue, $player)['rewards_available']);

        $strongDeal = Promotion::factory()->for($venue)->create([
            'organization_id' => $organization->getKey(),
            'resource_id' => $resource->getKey(),
            'discount_value' => '20.00',
        ]);
        $this->get(route('player.bookings.create', [
            'venueSlug' => $venue->slug,
            'resource' => $resource->getKey(),
            'date' => $wednesday,
            'start' => '09:00',
            'duration' => 60,
        ]))->assertOk()
            ->assertSee('Loyalty reward ready')
            ->assertSee($strongDeal->title)
            ->assertSee('data-online-total="₱520.00"', false)
            ->assertSee('data-loyalty-total="₱468.00"', false);

        $this->post(route('player.bookings.store', $venue->slug), [
            'resource_id' => $resource->getKey(),
            'booking_date' => $wednesday,
            'start_time' => '09:00',
            'duration_minutes' => 60,
            'payment_option' => 'online',
            'campaign' => $strongDeal->campaign_token,
            'loyalty_reward_version' => 1,
            'customer_name' => $player->name,
            'terms' => true,
        ])->assertRedirect();
        $stackedBooking = Booking::query()->latest('id')->firstOrFail();
        $this->assertSame($strongDeal->getKey(), $stackedBooking->promotion_id);
        $this->assertSame($strongDeal->campaign_token, $stackedBooking->promotion_campaign_token);
        $this->assertSame('468.00', $stackedBooking->total_amount);
        $this->assertSame('182.00', $stackedBooking->discount_amount);
        $this->assertDatabaseHas('loyalty_redemptions', [
            'booking_id' => $stackedBooking->getKey(), 'court_discount_amount' => '52.00',
        ]);
        $this->assertSame('468.00', $stackedBooking->payment->amount);
        $this->get(route('player.bookings.show', $stackedBooking->reference))->assertOk()
            ->assertSee('Your deal saved ₱130.00.')
            ->assertSee('Your loyalty reward saved another ₱52.00.');
    }

    public function test_owner_configured_discount_is_capped_without_a_daytime_rule(): void
    {
        $loyalty = app(VenueLoyalty::class);

        $quote = $loyalty->discountedQuote([
            'total_amount' => '1500.00',
            'discount_amount' => '0.00',
            'unit_price' => '1500.00',
        ], 60, '15.00', '200.00');
        $this->assertSame('200.00', $quote['discount']);
        $this->assertSame('1300.00', $quote['price']['total_amount']);
        $stackedQuote = $loyalty->discountedQuote([
            'total_amount' => '1200.00',
            'original_total_amount' => '1500.00',
            'discount_amount' => '300.00',
            'unit_price' => '1200.00',
        ], 60, '25.00', '200.00');
        $this->assertSame('200.00', $stackedQuote['discount']);
        $this->assertSame('1000.00', $stackedQuote['price']['total_amount']);
        $this->assertSame('500.00', $stackedQuote['price']['discount_amount']);
        $tinyQuote = $loyalty->discountedQuote([
            'total_amount' => '0.01',
            'discount_amount' => '0.00',
            'unit_price' => '0.01',
        ], 60, '0.01', '0.01');
        $this->assertSame('0.00', $tinyQuote['discount']);
    }

    public function test_changed_terms_leave_old_rewards_intact_and_can_be_used_on_a_weekend_evening(): void
    {
        [$organization, $venue, $resource, $owner] = $this->inventory(['loyalty_active' => true]);
        $player = User::factory()->create();
        foreach (range(1, 5) as $daysAgo) {
            $this->pastPaidGame($resource, $player, $daysAgo);
        }
        $this->artisan('loyalty:sync-stamps')->assertSuccessful();

        $this->actingAs($owner)->patch(route('owner.venues.loyalty.terms', $venue), [
            'stamps_required' => 8, 'discount_percent' => '15.00', 'discount_cap' => '200.00',
        ])->assertRedirect();
        $venue->refresh();
        $this->assertSame(2, $venue->loyalty_terms_version);
        $this->assertSame(1, app(VenueLoyalty::class)->balance($venue, $player)['rewards_available']);

        foreach (range(6, 13) as $daysAgo) {
            $this->pastPaidGame($resource, $player, $daysAgo);
        }
        $this->artisan('loyalty:sync-stamps')->assertSuccessful();
        $balance = app(VenueLoyalty::class)->balance($venue, $player);
        $this->assertSame(2, $balance['rewards_available']);
        $this->assertSame(5, $balance['rewards'][0]['stamps']);
        $this->assertSame('10.00', $balance['rewards'][0]['discount_percent']);
        $this->assertSame(8, $balance['rewards'][1]['stamps']);
        $this->assertSame('15.00', $balance['rewards'][1]['discount_percent']);

        $this->enableOnlinePayments();
        $saturday = now('Asia/Manila')->addWeek()->startOfWeek()->addDays(5)->toDateString();
        $this->actingAs($player)->get(route('player.bookings.create', [
            'venueSlug' => $venue->slug,
            'resource' => $resource->getKey(),
            'date' => $saturday,
            'start' => '19:00',
            'duration' => 60,
        ]))->assertOk()->assertSee('data-loyalty-total="₱585.00"', false);

        $booking = app(CreateBooking::class)->handle($organization->getKey(), $player, [
            'resource_id' => $resource->getKey(),
            'booking_date' => $saturday,
            'start_time' => '19:00',
            'end_time' => '20:00',
            'status' => BookingStatus::Hold->value,
            'source' => BookingSource::Marketplace->value,
            'customer_name' => $player->name,
            'create_payment' => true,
            'payment_provider' => 'paymongo',
            'loyalty_reward_version' => 1,
        ], $player);
        $this->assertSame('585.00', $booking->total_amount);
        $this->assertSame(2, $booking->loyalty_terms_version);
        $this->assertDatabaseHas('loyalty_redemptions', ['booking_id' => $booking->getKey(), 'terms_version' => 1]);
        $this->assertSame(1, app(VenueLoyalty::class)->balance($venue, $player)['rewards_available']);

        $newRewardBooking = app(CreateBooking::class)->handle($organization->getKey(), $player, [
            'resource_id' => $resource->getKey(),
            'booking_date' => now('Asia/Manila')->addWeek()->startOfWeek()->addDays(6)->toDateString(),
            'start_time' => '19:00',
            'end_time' => '20:00',
            'status' => BookingStatus::Hold->value,
            'source' => BookingSource::Marketplace->value,
            'customer_name' => $player->name,
            'create_payment' => true,
            'payment_provider' => 'paymongo',
            'loyalty_reward_version' => 2,
        ], $player);
        $this->assertSame('552.50', $newRewardBooking->total_amount);
        $this->assertDatabaseHas('loyalty_redemptions', ['booking_id' => $newRewardBooking->getKey(), 'terms_version' => 2]);
        $this->assertSame(0, app(VenueLoyalty::class)->balance($venue, $player)['rewards_available']);
    }

    public function test_terms_change_does_not_strand_a_partially_completed_stamp_card(): void
    {
        [, $venue, $resource, $owner] = $this->inventory(['loyalty_active' => true]);
        $player = User::factory()->create();
        foreach (range(1, 4) as $daysAgo) {
            $this->pastPaidGame($resource, $player, $daysAgo);
        }
        $this->artisan('loyalty:sync-stamps')->assertSuccessful();
        $this->assertSame(0, app(VenueLoyalty::class)->balance($venue, $player)['rewards_available']);

        $this->actingAs($owner)->patch(route('owner.venues.loyalty.terms', $venue), [
            'stamps_required' => 8, 'discount_percent' => '15.00', 'discount_cap' => '200.00',
        ])->assertRedirect();
        $venue->refresh();
        $newBooking = $this->pastPaidGame($resource, $player, 5);
        $this->assertSame(2, $newBooking->loyalty_terms_version);
        $this->artisan('loyalty:sync-stamps')->assertSuccessful();
        $this->assertDatabaseHas('loyalty_stamps', ['booking_id' => $newBooking->getKey(), 'terms_version' => 1]);
        $this->assertSame(1, app(VenueLoyalty::class)->balance($venue, $player)['rewards_available']);

        $nextBooking = $this->pastPaidGame($resource, $player, 6);
        $this->artisan('loyalty:sync-stamps')->assertSuccessful();
        $this->assertDatabaseHas('loyalty_stamps', ['booking_id' => $nextBooking->getKey(), 'terms_version' => 2]);
        $this->assertSame(1, app(VenueLoyalty::class)->balance($venue, $player)['current_stamps']);
    }

    public function test_pausing_stops_new_stamps_but_honors_a_booking_made_while_active(): void
    {
        [$organization, $venue, $resource] = $this->inventory(['loyalty_active' => true]);
        $player = User::factory()->create();
        $this->enableOnlinePayments();
        $monday = now('Asia/Manila')->addWeek()->startOfWeek()->toDateString();

        $promisedBooking = app(CreateBooking::class)->handle($organization->getKey(), $player, [
            'resource_id' => $resource->getKey(),
            'booking_date' => $monday,
            'start_time' => '09:00',
            'end_time' => '10:00',
            'status' => BookingStatus::Hold->value,
            'source' => BookingSource::Marketplace->value,
            'customer_name' => $player->name,
            'create_payment' => true,
            'payment_provider' => 'paymongo',
        ], $player);
        $this->assertTrue($promisedBooking->loyalty_eligible);

        $venue->update(['loyalty_active' => false]);
        $newBooking = app(CreateBooking::class)->handle($organization->getKey(), $player, [
            'resource_id' => $resource->getKey(),
            'booking_date' => now('Asia/Manila')->addWeek()->startOfWeek()->addDay()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'status' => BookingStatus::Hold->value,
            'source' => BookingSource::Marketplace->value,
            'customer_name' => $player->name,
            'create_payment' => true,
            'payment_provider' => 'paymongo',
        ], $player);
        $this->assertFalse($newBooking->loyalty_eligible);

        $playedAt = now('Asia/Manila')->subDay()->setTime(9, 0)->utc();
        $promisedBooking->update([
            'status' => BookingStatus::Confirmed,
            'payment_status' => PaymentStatus::Paid,
            'expires_at' => null,
            'start_at' => $playedAt,
            'end_at' => $playedAt->copy()->addHour(),
        ]);
        $this->artisan('loyalty:sync-stamps')->assertSuccessful();
        $this->assertSame(1, app(VenueLoyalty::class)->balance($venue, $player)['stamps']);
    }

    /** @return array{Organization, Venue, CourtResource, User} */
    private function inventory(array $venueAttributes = []): array
    {
        $organization = Organization::factory()->create(['timezone' => 'Asia/Manila']);
        $owner = User::factory()->create();
        Membership::factory()->owner()->for($owner)->for($organization)->create();
        $venue = Venue::factory()->for($organization)->published()->create([
            'name' => 'Loyalty Test Courts',
            'slug' => 'loyalty-test-courts',
            'city' => 'Makati',
            'city_slug' => 'makati',
            'province' => 'Metro Manila',
            'province_slug' => 'metro-manila',
            ...$venueAttributes,
        ]);
        $sport = Sport::factory()->create(['name' => 'Badminton', 'slug' => 'badminton']);
        $resource = CourtResource::factory()->for($venue)->for($sport)->create([
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

    private function pastPaidGame(CourtResource $resource, User $player, int $daysAgo, int $hour = 9): Booking
    {
        $start = now('Asia/Manila')->subDays($daysAgo)->setTime($hour, 0)->utc();
        $venue = $resource->venue()->firstOrFail();

        return Booking::factory()->for($resource, 'resource')->create([
            'player_user_id' => $player->getKey(),
            'created_by_user_id' => $player->getKey(),
            'source' => BookingSource::Marketplace,
            'status' => BookingStatus::Confirmed,
            'payment_mode' => PaymentMode::HostedCheckout,
            'payment_status' => PaymentStatus::Paid,
            'loyalty_eligible' => true,
            'loyalty_terms_version' => $venue->loyalty_terms_version,
            'loyalty_stamps_required' => $venue->loyalty_stamps_required,
            'loyalty_discount_percent' => $venue->loyalty_discount_percent,
            'loyalty_discount_cap' => $venue->loyalty_discount_cap,
            'start_at' => $start,
            'end_at' => $start->copy()->addHour(),
        ]);
    }

    private function refundRequestFor(Booking $booking, Payment $payment, RefundRequestStatus $status): RefundRequest
    {
        return RefundRequest::query()->create([
            'organization_id' => $booking->organization_id,
            'booking_id' => $booking->getKey(),
            'payment_id' => $payment->getKey(),
            'reference' => 'RFD-'.Str::ulid(),
            'status' => $status,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'reason' => 'Testing the stamp refund safeguard.',
            'provider' => $payment->provider,
            'requested_at' => now(),
        ]);
    }

    private function enableOnlinePayments(): void
    {
        config()->set('payments.default', 'paymongo');
        config()->set('payments.online_provider', 'paymongo');
        config()->set('payments.providers.paymongo.enabled', true);
        config()->set('payments.providers.paymongo.mode', 'test');
        config()->set('payments.providers.paymongo.secret_key', 'sk_test_fincourt');
        config()->set('payments.providers.paymongo.webhook_secret', 'whsk_test_fincourt');
    }
}
