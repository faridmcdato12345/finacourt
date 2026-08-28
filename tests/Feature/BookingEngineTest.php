<?php

namespace Tests\Feature;

use App\Bookings\CreateBooking;
use App\Enums\BookingStatus;
use App\Enums\OrganizationPermission;
use App\Models\Booking;
use App\Models\CourtResource;
use App\Models\Membership;
use App\Models\OperatingHour;
use App\Models\Organization;
use App\Models\Sport;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_booking_within_operating_hours_succeeds_with_reference_and_price_snapshot(): void
    {
        [$owner, $resource] = $this->bookingSetup(['booking_increment_minutes' => 30]);

        $this->actingAs($owner)
            ->post(route('owner.bookings.store'), [
                ...$this->bookingData($resource),
                'start_time' => '09:00',
                'end_time' => '10:30',
            ])->assertRedirect();

        $booking = Booking::query()->firstOrFail();
        $this->assertSame($resource->venue->organization_id, $booking->organization_id);
        $this->assertSame($resource->venue_id, $booking->venue_id);
        $this->assertStringStartsWith('BK-', $booking->reference);
        $this->assertSame('650.00', $booking->unit_price);
        $this->assertSame('975.00', $booking->total_amount);
        $this->assertSame('01:00', $booking->start_at->format('H:i'));
        $this->assertSame('02:30', $booking->end_at->format('H:i'));
        $this->assertSame('Asia/Manila', $booking->timezone);
    }

    public function test_booking_outside_operating_hours_fails(): void
    {
        [$owner, $resource] = $this->bookingSetup();

        $this->actingAs($owner)
            ->post(route('owner.bookings.store'), [
                ...$this->bookingData($resource),
                'start_time' => '07:00',
                'end_time' => '08:00',
            ])->assertSessionHasErrors('start_time');

        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_inactive_resource_cannot_be_booked(): void
    {
        [$owner, $resource] = $this->bookingSetup(['is_active' => false]);

        $this->actingAs($owner)
            ->post(route('owner.bookings.store'), $this->bookingData($resource))
            ->assertSessionHasErrors('resource_id');

        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_exact_adjacent_bookings_do_not_conflict(): void
    {
        [$owner, $resource] = $this->bookingSetup();
        $this->createThroughEngine($owner, $resource, ['start_time' => '09:00', 'end_time' => '10:00']);

        $this->actingAs($owner)
            ->post(route('owner.bookings.store'), [
                ...$this->bookingData($resource),
                'start_time' => '10:00',
                'end_time' => '11:00',
            ])->assertRedirect();

        $this->assertDatabaseCount('bookings', 2);
    }

    public function test_overlapping_confirmed_booking_is_rejected(): void
    {
        [$owner, $resource] = $this->bookingSetup(['booking_increment_minutes' => 30]);
        $this->createThroughEngine($owner, $resource, ['start_time' => '09:00', 'end_time' => '10:30']);

        $this->actingAs($owner)
            ->post(route('owner.bookings.store'), [
                ...$this->bookingData($resource),
                'start_time' => '10:00',
                'end_time' => '11:00',
            ])->assertSessionHasErrors('start_time');

        $this->assertDatabaseCount('bookings', 1);
    }

    public function test_active_hold_blocks_but_expired_hold_does_not(): void
    {
        [$owner, $resource] = $this->bookingSetup();
        $hold = $this->createThroughEngine($owner, $resource, [
            'status' => BookingStatus::Hold->value,
            'hold_minutes' => 15,
        ]);

        $this->actingAs($owner)
            ->post(route('owner.bookings.store'), $this->bookingData($resource))
            ->assertSessionHasErrors('start_time');

        $hold->update(['expires_at' => now()->subSecond()]);
        $this->actingAs($owner)
            ->post(route('owner.bookings.store'), $this->bookingData($resource))
            ->assertRedirect();

        $this->assertDatabaseCount('bookings', 2);
        $this->assertSame(BookingStatus::Expired, $hold->refresh()->effectiveStatus());
    }

    public function test_cancellation_releases_availability(): void
    {
        [$owner, $resource] = $this->bookingSetup();
        $booking = $this->createThroughEngine($owner, $resource);

        $this->actingAs($owner)
            ->patch(route('owner.bookings.cancel', $booking), ['cancellation_reason' => 'Customer called'])
            ->assertRedirect();

        $this->assertSame(BookingStatus::Cancelled, $booking->refresh()->status);
        $this->assertNotNull($booking->cancelled_at);

        $this->actingAs($owner)
            ->post(route('owner.bookings.store'), $this->bookingData($resource))
            ->assertRedirect();
        $this->assertDatabaseCount('bookings', 2);
    }

    public function test_price_snapshot_does_not_change_when_resource_price_changes(): void
    {
        [$owner, $resource] = $this->bookingSetup();
        $booking = $this->createThroughEngine($owner, $resource);

        $resource->update(['base_hourly_rate' => '900.00']);

        $this->assertSame('650.00', $booking->refresh()->unit_price);
        $this->assertSame('650.00', $booking->total_amount);
    }

    public function test_duration_and_slot_alignment_are_enforced(): void
    {
        [$owner, $resource] = $this->bookingSetup(['booking_increment_minutes' => 30]);

        $this->actingAs($owner)
            ->post(route('owner.bookings.store'), [
                ...$this->bookingData($resource),
                'start_time' => '09:15',
                'end_time' => '10:00',
            ])->assertSessionHasErrors('start_time');

        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_availability_returns_blocked_and_adjacent_slots(): void
    {
        [$owner, $resource] = $this->bookingSetup();
        $this->createThroughEngine($owner, $resource);

        $response = $this->actingAs($owner)->getJson(route('owner.bookings.availability', [
            'resource_id' => $resource->getKey(),
            'date' => $this->futureDate(),
            'duration_minutes' => 60,
        ]));

        $response->assertOk()
            ->assertJsonPath('timezone', 'Asia/Manila')
            ->assertJsonFragment(['start_time' => '09:00', 'end_time' => '10:00', 'available' => false])
            ->assertJsonFragment(['start_time' => '10:00', 'end_time' => '11:00', 'available' => true]);
    }

    public function test_tenant_cannot_view_cancel_or_book_another_tenants_inventory(): void
    {
        [$ownerA] = $this->bookingSetup();
        [$ownerB, $resourceB] = $this->bookingSetup();
        $bookingB = $this->createThroughEngine($ownerB, $resourceB);

        $this->actingAs($ownerA)
            ->get(route('owner.bookings.index', ['date' => $this->futureDate()]))
            ->assertOk()
            ->assertDontSee($bookingB->reference);
        $this->actingAs($ownerA)
            ->patch(route('owner.bookings.cancel', $bookingB))
            ->assertNotFound();
        $this->actingAs($ownerA)
            ->post(route('owner.bookings.store'), $this->bookingData($resourceB))
            ->assertNotFound();

        $this->assertSame(BookingStatus::Confirmed, $bookingB->refresh()->status);
    }

    public function test_staff_needs_explicit_booking_permission(): void
    {
        [, $resource] = $this->bookingSetup();
        $organization = $resource->venue->organization;
        $readOnlyStaff = User::factory()->create();
        $bookingStaff = User::factory()->create();
        Membership::factory()->for($readOnlyStaff)->for($organization)
            ->withPermissions([OrganizationPermission::ViewDashboard])->create();
        Membership::factory()->for($bookingStaff)->for($organization)
            ->withPermissions([
                OrganizationPermission::ViewDashboard,
                OrganizationPermission::ManageBookings,
            ])->create();

        $this->actingAs($readOnlyStaff)->get(route('owner.bookings.index'))->assertForbidden();
        $this->actingAs($bookingStaff)->get(route('owner.bookings.index'))->assertOk();
    }

    public function test_booking_factory_derives_tenant_and_venue_from_resource(): void
    {
        [$owner, $resource] = $this->bookingSetup();

        $booking = Booking::factory()->for($resource, 'resource')->create([
            'created_by_user_id' => $owner->getKey(),
        ]);

        $this->assertSame($resource->venue_id, $booking->venue_id);
        $this->assertSame($resource->venue->organization_id, $booking->organization_id);
    }

    /** @param array<string, mixed> $resourceAttributes
     * @return array{User, CourtResource}
     */
    private function bookingSetup(array $resourceAttributes = []): array
    {
        $owner = User::factory()->create();
        $organization = Organization::factory()->create(['timezone' => 'Asia/Manila']);
        Membership::factory()->owner()->for($owner)->for($organization)->create();
        $venue = Venue::factory()->for($organization)->create();
        $sport = Sport::factory()->create();
        $venue->sports()->attach($sport);
        $resource = CourtResource::factory()->for($venue)->for($sport)->create([
            'base_hourly_rate' => '650.00',
            'booking_increment_minutes' => 60,
            'is_active' => true,
            ...$resourceAttributes,
        ]);

        foreach (range(0, 6) as $day) {
            OperatingHour::factory()->for($venue)->create([
                'day_of_week' => $day,
                'is_closed' => false,
                'opens_at' => '08:00',
                'closes_at' => '22:00',
            ]);
        }

        return [$owner, $resource];
    }

    /** @param array<string, mixed> $overrides */
    private function createThroughEngine(User $owner, CourtResource $resource, array $overrides = []): Booking
    {
        return app(CreateBooking::class)->handle(
            $resource->venue->organization_id,
            $owner,
            [...$this->bookingData($resource), ...$overrides],
        );
    }

    /** @return array<string, mixed> */
    private function bookingData(CourtResource $resource): array
    {
        return [
            'resource_id' => $resource->getKey(),
            'booking_date' => $this->futureDate(),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'status' => BookingStatus::Confirmed->value,
            'source' => 'manual',
            'hold_minutes' => null,
            'customer_name' => 'Jamie Player',
            'customer_email' => 'jamie@example.com',
            'customer_phone' => '+63 900 000 0000',
            'notes' => 'Manual test booking',
        ];
    }

    private function futureDate(): string
    {
        return now('Asia/Manila')->addDays(7)->toDateString();
    }
}
