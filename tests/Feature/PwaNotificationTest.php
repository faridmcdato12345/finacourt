<?php

namespace Tests\Feature;

use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\PaymentMode;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\CourtResource;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Sport;
use App\Models\User;
use App\Models\Venue;
use App\Notifications\BookingNotifier;
use App\Notifications\Contracts\WebPushGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PwaNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_manifest_and_install_icons_are_valid_repository_assets(): void
    {
        $manifest = json_decode(file_get_contents(public_path('manifest.webmanifest')), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('FinACourt', $manifest['name']);
        $this->assertSame('standalone', $manifest['display']);
        $this->assertSame('/', $manifest['scope']);
        $this->assertSame('#146d4a', $manifest['theme_color']);
        $this->assertCount(3, $manifest['icons']);
        $this->assertSame('/icons/finacourt-logo-192.png', $manifest['icons'][0]['src']);
        $this->assertSame('/icons/finacourt-logo-512.png', $manifest['icons'][1]['src']);
        $this->assertSame('/icons/finacourt-logo-maskable-512.png', $manifest['icons'][2]['src']);

        foreach ($manifest['icons'] as $icon) {
            $path = public_path(ltrim($icon['src'], '/'));
            $this->assertFileExists($path);
            $this->assertSame('image/png', mime_content_type($path));
            [$width, $height] = getimagesize($path);
            $this->assertSame($icon['sizes'], "{$width}x{$height}");
        }
    }

    public function test_service_worker_registration_and_cache_boundaries_are_explicit(): void
    {
        $registration = file_get_contents(resource_path('js/pwa.js'));
        $worker = file_get_contents(public_path('sw.js'));
        $offline = file_get_contents(public_path('offline.html'));

        $this->assertStringContainsString("serviceWorker.register('/sw.js'", $registration);
        $this->assertStringContainsString("request.method !== 'GET'", $worker);
        $this->assertStringContainsString("'/owner', '/platform', '/player', '/booking'", $worker);
        $this->assertStringContainsString("'/venues'", $worker);
        $this->assertStringContainsString("response.headers.get('x-pwa-cache') === 'public-short'", $worker);
        $this->assertStringContainsString('Reservations cannot be created offline', $offline);
        $this->assertStringNotContainsString('backgroundSync', $worker);
    }

    public function test_response_headers_allow_only_narrow_public_caching(): void
    {
        [$organization, $venue, , $owner] = $this->inventory();

        $this->get(route('marketplace.home'))
            ->assertOk()
            ->assertHeader('X-PWA-Cache', 'public-short');
        $filtered = $this->get(route('marketplace.courts.index', ['source' => 'pwa']));
        $filtered->assertHeader('X-PWA-Cache', 'network-only');
        $this->assertStringContainsString('no-store', (string) $filtered->headers->get('Cache-Control'));
        $venueResponse = $this->get(route('marketplace.venues.show', $venue->slug));
        $venueResponse
            ->assertOk()
            ->assertHeader('X-PWA-Cache', 'network-only');
        $this->assertStringContainsString('no-store', (string) $venueResponse->headers->get('Cache-Control'));
        $this->actingAs($owner)->get(route('owner.dashboard'))
            ->assertOk()
            ->assertHeader('X-PWA-Cache', 'network-only');
        $this->assertSame($organization->getKey(), session('tenant.organization_id'));
    }

    public function test_booking_confirmation_notification_is_idempotent(): void
    {
        [$organization, $venue, $resource] = $this->inventory();
        $player = User::factory()->create();
        $booking = $this->booking($organization, $venue, $resource, $player, now()->addDays(2), BookingStatus::Hold);
        $booking->update([
            'expires_at' => now()->addMinutes(15),
            'payment_mode' => PaymentMode::PayAtVenue,
            'payment_status' => PaymentStatus::Pending,
        ]);

        $this->actingAs($player)->post(route('player.bookings.confirm', $booking->reference))->assertRedirect();
        $this->actingAs($player)->post(route('player.bookings.confirm', $booking->reference))->assertRedirect();

        $this->assertDatabaseCount('notifications', 1);
        $this->assertNotNull($booking->refresh()->confirmation_notified_at);
        $this->assertSame('booking_confirmed', $player->notifications()->firstOrFail()->data['kind']);
    }

    public function test_payment_notification_is_idempotent_and_user_scoped(): void
    {
        [$organization, $venue, $resource] = $this->inventory();
        $player = User::factory()->create();
        $otherPlayer = User::factory()->create();
        $booking = $this->booking($organization, $venue, $resource, $player, now()->addDays(2));
        $notifier = app(BookingNotifier::class);

        $notifier->paymentReceived($booking);
        $notifier->paymentReceived($booking->refresh());

        $notification = $player->notifications()->firstOrFail();
        $this->assertDatabaseCount('notifications', 1);
        $this->actingAs($otherPlayer)
            ->patch(route('player.notifications.read', $notification->getKey()))
            ->assertNotFound();
        $this->actingAs($player)
            ->patch(route('player.notifications.read', $notification->getKey()))
            ->assertRedirect();
        $this->assertNotNull($notification->refresh()->read_at);
    }

    public function test_scheduled_reminders_send_once_only_for_due_confirmed_player_bookings(): void
    {
        [$organization, $venue, $resource] = $this->inventory();
        $duePlayer = User::factory()->create();
        $cancelledPlayer = User::factory()->create();
        $due = $this->booking($organization, $venue, $resource, $duePlayer, now()->addDay());
        $this->booking(
            $organization,
            $venue,
            $resource,
            $cancelledPlayer,
            now()->addDay(),
            BookingStatus::Cancelled,
        );

        $this->artisan('bookings:send-reminders')->assertSuccessful();
        $this->artisan('bookings:send-reminders')->assertSuccessful();

        $this->assertDatabaseCount('notifications', 1);
        $this->assertSame('booking_reminder', $duePlayer->notifications()->firstOrFail()->data['kind']);
        $this->assertNotNull($due->refresh()->reminder_notified_at);
        $this->assertSame(0, $cancelledPlayer->notifications()->count());
    }

    public function test_remote_push_is_not_emitted_when_the_enclosing_transaction_rolls_back(): void
    {
        [$organization, $venue, $resource] = $this->inventory();
        $player = User::factory()->create();
        $booking = $this->booking($organization, $venue, $resource, $player, now()->addDays(2));
        $push = new class implements WebPushGateway
        {
            public int $sent = 0;

            public function send(User $user, array $payload): void
            {
                $this->sent++;
            }
        };
        $this->app->instance(WebPushGateway::class, $push);

        try {
            DB::transaction(function () use ($booking): void {
                app(BookingNotifier::class)->paymentReceived($booking);

                throw new \RuntimeException('force rollback');
            });
        } catch (\RuntimeException $exception) {
            $this->assertSame('force rollback', $exception->getMessage());
        }

        $this->assertSame(0, $push->sent);
        $this->assertDatabaseCount('notifications', 0);
        $this->assertNull($booking->refresh()->payment_notified_at);
    }

    /** @return array{Organization, Venue, CourtResource, User} */
    private function inventory(): array
    {
        $organization = Organization::factory()->create(['timezone' => 'Asia/Manila']);
        $owner = User::factory()->create();
        Membership::factory()->owner()->for($owner)->for($organization)->create();
        $venue = Venue::factory()->published()->for($organization)->create([
            'slug' => fake()->unique()->slug(3),
            'city' => 'Makati',
            'city_slug' => 'makati',
        ]);
        $sport = Sport::factory()->create(['is_active' => true]);
        $resource = CourtResource::factory()->for($venue)->for($sport)->create();

        return [$organization, $venue, $resource, $owner];
    }

    private function booking(
        Organization $organization,
        Venue $venue,
        CourtResource $resource,
        User $player,
        mixed $start,
        BookingStatus $status = BookingStatus::Confirmed,
    ): Booking {
        return Booking::factory()->for($resource, 'resource')->create([
            'organization_id' => $organization->getKey(),
            'venue_id' => $venue->getKey(),
            'player_user_id' => $player->getKey(),
            'source' => BookingSource::Marketplace,
            'status' => $status,
            'start_at' => $start,
            'end_at' => $start->copy()->addHour(),
            'payment_status' => PaymentStatus::Pending,
        ]);
    }
}
