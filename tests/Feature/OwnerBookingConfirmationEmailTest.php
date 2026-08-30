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
use App\Notifications\OwnerBookingConfirmedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class OwnerBookingConfirmationEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_pay_at_venue_confirmation_emails_each_owner_once_and_never_emails_staff_or_another_tenant(): void
    {
        Notification::fake();

        $organization = Organization::factory()->create([
            'name' => 'Owner Email Courts',
            'timezone' => 'Asia/Manila',
        ]);
        $owner = User::factory()->create(['name' => 'Olivia Owner']);
        $secondOwner = User::factory()->create(['name' => 'Oscar Owner']);
        $staff = User::factory()->create();
        Membership::factory()->owner()->for($owner)->for($organization)->create();
        Membership::factory()->owner()->for($secondOwner)->for($organization)->create();
        Membership::factory()->for($staff)->for($organization)->create();

        $otherOrganization = Organization::factory()->create();
        $otherOwner = User::factory()->create();
        Membership::factory()->owner()->for($otherOwner)->for($otherOrganization)->create();

        $venue = Venue::factory()->for($organization)->create(['name' => 'Marawi Court Hub']);
        $sport = Sport::factory()->create();
        $resource = CourtResource::factory()->for($venue)->for($sport)->create(['name' => 'Pickleball Court 1']);
        $player = User::factory()->create();
        $start = now('Asia/Manila')->addDays(2)->setTime(17, 0)->utc();
        $booking = Booking::factory()->for($resource, 'resource')->create([
            'organization_id' => $organization->getKey(),
            'venue_id' => $venue->getKey(),
            'player_user_id' => $player->getKey(),
            'source' => BookingSource::Marketplace,
            'status' => BookingStatus::Hold,
            'start_at' => $start,
            'end_at' => $start->copy()->addHours(2),
            'expires_at' => now()->addMinutes(15),
            'timezone' => 'Asia/Manila',
            'customer_name' => 'Paolo Player',
            'customer_email' => 'paolo@example.com',
            'customer_phone' => '09171234567',
            'payment_mode' => PaymentMode::PayAtVenue,
            'payment_status' => PaymentStatus::Pending,
            'total_amount' => '1200.00',
            'player_total_amount' => '1200.00',
        ]);

        $this->actingAs($player)
            ->post(route('player.bookings.confirm', $booking->reference))
            ->assertRedirect(route('player.bookings.show', $booking->reference));
        $this->actingAs($player)
            ->post(route('player.bookings.confirm', $booking->reference))
            ->assertRedirect(route('player.bookings.show', $booking->reference));

        Notification::assertSentToTimes($owner, OwnerBookingConfirmedNotification::class, 1);
        Notification::assertSentToTimes($secondOwner, OwnerBookingConfirmedNotification::class, 1);
        Notification::assertNotSentTo($staff, OwnerBookingConfirmedNotification::class);
        Notification::assertNotSentTo($otherOwner, OwnerBookingConfirmedNotification::class);
        Notification::assertSentTo($owner, function (OwnerBookingConfirmedNotification $notification, array $channels) use ($booking): bool {
            return $channels === ['mail']
                && $notification->bookingReference === $booking->reference
                && $notification->paymentLabel === 'Pay at the venue'
                && $notification->venueName === 'Marawi Court Hub'
                && $notification->courtName === 'Pickleball Court 1'
                && $notification->playerName === 'Paolo Player'
                && $notification->queue === 'emails'
                && $notification->afterCommit === true;
        });

        $booking->refresh();
        $this->assertSame(BookingStatus::Confirmed, $booking->status);
        $this->assertNotNull($booking->owner_confirmation_notified_at);
    }
}
