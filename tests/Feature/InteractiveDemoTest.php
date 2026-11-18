<?php

namespace Tests\Feature;

use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\PaymentMode;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\InteractiveDemoWorkspace;
use App\Models\Payment;
use App\Models\ReservationGroup;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class InteractiveDemoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('interactive-demo.enabled', true);
        config()->set('interactive-demo.workspace_lifetime_hours', 12);
    }

    public function test_demo_is_hidden_when_disabled(): void
    {
        config()->set('interactive-demo.enabled', false);

        $this->get(route('interactive-demo.show'))->assertNotFound();
    }

    public function test_owner_acquisition_demo_returns_to_the_owner_page_on_exit(): void
    {
        $this->get(route('interactive-demo.show', ['return_to' => 'owners']))
            ->assertOk()
            ->assertSessionHas('interactive_demo.exit_route', 'marketplace.for-owners');

        $this->post(route('interactive-demo.start'), ['role' => 'player']);

        $this->post(route('interactive-demo.exit'))
            ->assertRedirect(route('marketplace.for-owners'))
            ->assertSessionMissing('interactive_demo.workspace_id')
            ->assertSessionMissing('interactive_demo.exit_route');
    }

    public function test_starting_a_demo_creates_a_private_player_and_owner_workspace(): void
    {
        Mail::fake();

        $this->post(route('interactive-demo.start'), ['role' => 'player'])
            ->assertRedirect(route('interactive-demo.player'));

        $workspace = InteractiveDemoWorkspace::query()->with(['owner', 'player', 'organization', 'venue'])->sole();
        $this->assertAuthenticatedAs($workspace->player);
        $this->assertFalse($workspace->owner->is_platform_admin);
        $this->assertFalse($workspace->player->is_platform_admin);
        $this->assertSame($workspace->organization_id, $workspace->venue->organization_id);
        $this->assertDatabaseHas('memberships', [
            'organization_id' => $workspace->organization_id,
            'user_id' => $workspace->owner_user_id,
            'role' => 'owner',
        ]);
        $this->assertDatabaseCount('resources', 2);
        $this->assertDatabaseCount('operating_hours', 7);
        Mail::assertNothingOutgoing();
    }

    public function test_player_demo_uses_the_phone_emulator_and_denies_the_owner_identity(): void
    {
        $this->post(route('interactive-demo.start'), ['role' => 'player']);
        $workspace = InteractiveDemoWorkspace::query()->sole();

        $this->get(route('interactive-demo.player'))
            ->assertOk()
            ->assertSee('iPhone 17 Pro Max')
            ->assertSee('data-player-demo-screen', false)
            ->assertSee($workspace->venue->slug)
            ->assertSee($workspace->session_token);

        $this->post(route('interactive-demo.switch', 'owner'));
        $this->get(route('interactive-demo.player'))->assertNotFound();
    }

    public function test_demo_venue_is_hidden_from_public_catalogues_but_available_to_its_active_workspace(): void
    {
        $this->post(route('interactive-demo.start'), ['role' => 'player']);
        $workspace = InteractiveDemoWorkspace::query()->with('venue')->sole();
        $venueUrl = route('marketplace.venues.show', $workspace->venue->slug);

        $this->get($venueUrl)
            ->assertOk()
            ->assertSee($workspace->venue->name)
            ->assertSee('Demo weekday 15% off');

        $this->post(route('interactive-demo.exit'));

        $this->get(route('marketplace.courts.index'))
            ->assertOk()
            ->assertDontSee($workspace->venue->name);
        $this->get(route('marketplace.deals'))
            ->assertOk()
            ->assertDontSee('Demo weekday 15% off');
        $this->get($venueUrl)->assertNotFound();
    }

    public function test_demo_player_is_confined_to_its_private_venue_and_cannot_start_or_pay_for_live_bookings(): void
    {
        $this->post(route('interactive-demo.start'), ['role' => 'player']);
        $workspace = InteractiveDemoWorkspace::query()->with('venue.resources')->sole();
        $liveVenue = Venue::factory()->published()->verified()->create();

        foreach ([
            route('marketplace.home'),
            route('marketplace.courts.index'),
            route('marketplace.deals'),
            route('marketplace.venues.show', $liveVenue->slug),
        ] as $url) {
            $response = $this->get($url);

            $response->assertRedirect();
            $this->assertStringContainsString($workspace->venue->slug, (string) $response->headers->get('Location'));
            $this->assertStringContainsString('demo_workspace=', (string) $response->headers->get('Location'));
        }

        $this->get(route('player.bookings.create', [
            'venueSlug' => $liveVenue->slug,
            'resource' => 999999,
            'date' => now('Asia/Manila')->addDay()->toDateString(),
            'start' => '10:00',
            'duration' => 60,
        ]))->assertNotFound();

        // Simulate a historical/crafted booking for the demo player at a live
        // venue. It must never gain access to simulated checkout.
        $booking = $this->makeHold($workspace, $workspace->venue->resources->first());
        $booking->update([
            'organization_id' => $liveVenue->organization_id,
            'venue_id' => $liveVenue->getKey(),
        ]);

        $this->post(route('player.bookings.checkout', $booking->reference))->assertNotFound();
    }

    public function test_multiple_demo_workspaces_receive_unique_seed_payment_references(): void
    {
        $this->post(route('interactive-demo.start'), ['role' => 'player'])->assertRedirect();
        $this->post(route('interactive-demo.start'), ['role' => 'owner'])->assertRedirect();

        $references = Payment::query()
            ->where('provider', 'demo')
            ->pluck('provider_reference');

        $this->assertCount(2, $references);
        $this->assertCount(2, $references->unique());
    }

    public function test_demo_can_switch_to_its_owner_workspace_without_exposing_platform_administration(): void
    {
        $this->post(route('interactive-demo.start'), ['role' => 'player']);
        $workspace = InteractiveDemoWorkspace::query()->sole();

        $this->post(route('interactive-demo.switch', 'owner'))
            ->assertRedirect(route('owner.dashboard'));

        $this->assertAuthenticatedAs($workspace->owner);
        $this->assertSame($workspace->organization_id, session('tenant.organization_id'));
        $this->get(route('owner.dashboard'))
            ->assertOk()
            ->assertDontSee('Platform administration');
    }

    public function test_demo_checkout_confirms_a_single_hold_without_contacting_a_provider_or_sending_email(): void
    {
        Http::fake();
        Mail::fake();
        $this->post(route('interactive-demo.start'), ['role' => 'player']);
        $workspace = InteractiveDemoWorkspace::query()->with(['venue.resources', 'player'])->sole();
        $booking = $this->makeHold($workspace, $workspace->venue->resources->first());
        $payment = Payment::query()->create([
            'organization_id' => $workspace->organization_id,
            'booking_id' => $booking->getKey(),
            'reference' => 'PAY-DEMO-SINGLE',
            'provider' => 'demo',
            'mode' => PaymentMode::HostedCheckout,
            'status' => PaymentStatus::Pending,
            'amount' => '600.00',
            'venue_amount' => '600.00',
            'platform_service_fee_amount' => '0.00',
            'refunded_amount' => '0.00',
            'currency' => 'PHP',
            'created_by_user_id' => $workspace->player_user_id,
        ]);

        $this->post(route('player.bookings.checkout', $booking->reference))
            ->assertRedirect(route('player.bookings.show', $booking->reference))
            ->assertSessionHas('status', 'Demo payment complete. This reservation is confirmed; no real charge, email, or provider call was made.');

        $this->assertDatabaseHas('bookings', ['id' => $booking->getKey(), 'status' => 'confirmed', 'payment_status' => 'paid']);
        $this->assertDatabaseHas('payments', ['id' => $payment->getKey(), 'status' => 'paid']);
        $this->assertDatabaseHas('payment_transitions', ['payment_id' => $payment->getKey(), 'source' => 'interactive_demo']);
        Http::assertNothingSent();
        Mail::assertNothingOutgoing();
    }

    public function test_demo_checkout_confirms_every_court_in_a_grouped_hold(): void
    {
        $this->post(route('interactive-demo.start'), ['role' => 'player']);
        $workspace = InteractiveDemoWorkspace::query()->with(['venue.resources'])->sole();
        $group = ReservationGroup::query()->create([
            'organization_id' => $workspace->organization_id,
            'venue_id' => $workspace->venue_id,
            'player_user_id' => $workspace->player_user_id,
            'reference' => 'RG-DEMO-GROUP',
            'status' => BookingStatus::Hold,
            'expires_at' => now()->addMinutes(10),
        ]);
        $first = $this->makeHold($workspace, $workspace->venue->resources[0], $group->getKey(), 'BK-DEMO-GROUP-ONE');
        $second = $this->makeHold($workspace, $workspace->venue->resources[1], $group->getKey(), 'BK-DEMO-GROUP-TWO');
        Payment::query()->create([
            'organization_id' => $workspace->organization_id,
            'reservation_group_id' => $group->getKey(),
            'booking_id' => $first->getKey(),
            'reference' => 'PAY-DEMO-GROUP',
            'provider' => 'demo',
            'mode' => PaymentMode::HostedCheckout,
            'status' => PaymentStatus::Pending,
            'amount' => '1300.00',
            'venue_amount' => '1300.00',
            'platform_service_fee_amount' => '0.00',
            'refunded_amount' => '0.00',
            'currency' => 'PHP',
            'created_by_user_id' => $workspace->player_user_id,
        ]);

        $this->post(route('player.bookings.checkout', $first->reference))->assertRedirect();

        $this->assertDatabaseHas('reservation_groups', ['id' => $group->getKey(), 'status' => 'confirmed']);
        $this->assertDatabaseHas('bookings', ['id' => $first->getKey(), 'status' => 'confirmed', 'payment_status' => 'paid']);
        $this->assertDatabaseHas('bookings', ['id' => $second->getKey(), 'status' => 'confirmed', 'payment_status' => 'paid']);
    }

    private function makeHold(InteractiveDemoWorkspace $workspace, mixed $resource, ?int $groupId = null, ?string $reference = null): Booking
    {
        $start = now('Asia/Manila')->addDays(2)->setTime(10, 0)->utc();

        return Booking::query()->create([
            'organization_id' => $workspace->organization_id,
            'reservation_group_id' => $groupId,
            'venue_id' => $workspace->venue_id,
            'resource_id' => $resource->getKey(),
            'player_user_id' => $workspace->player_user_id,
            'reference' => $reference ?? 'BK-DEMO-SINGLE',
            'status' => BookingStatus::Hold,
            'source' => BookingSource::Marketplace,
            'customer_name' => 'Demo Player',
            'customer_email' => 'demo@example.test',
            'start_at' => $start,
            'end_at' => $start->addHour(),
            'expires_at' => now()->addMinutes(10),
            'timezone' => 'Asia/Manila',
            'unit_price' => '600.00',
            'original_unit_price' => '600.00',
            'total_amount' => '600.00',
            'original_total_amount' => '600.00',
            'discount_amount' => '0.00',
            'platform_service_fee_amount' => '0.00',
            'player_total_amount' => '600.00',
            'currency' => 'PHP',
            'payment_mode' => PaymentMode::HostedCheckout,
            'payment_status' => PaymentStatus::Pending,
            'created_by_user_id' => $workspace->player_user_id,
        ]);
    }
}
