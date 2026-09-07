<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\CourtAvailabilityBlock;
use App\Models\CourtResource;
use App\Models\Membership;
use App\Models\OperatingHour;
use App\Models\Organization;
use App\Models\Sport;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourtAvailabilityBlockTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_block_a_court_with_a_reason_and_players_cannot_book_it(): void
    {
        [$owner, $resource] = $this->bookingSetup();

        $this->actingAs($owner)
            ->post(route('owner.booking-blocks.store'), $this->blockData($resource))
            ->assertRedirect(route('owner.bookings.index', ['date' => $this->futureDate()]));

        $block = CourtAvailabilityBlock::query()->firstOrFail();
        $this->assertSame('Court resurfacing', $block->reason);
        $this->assertSame('Asia/Manila', $block->timezone);
        $this->assertSame('01:00', $block->starts_at->format('H:i'));
        $this->assertSame('02:00', $block->ends_at->format('H:i'));

        $this->actingAs($owner)
            ->getJson(route('owner.bookings.availability', [
                'resource_id' => $resource->getKey(),
                'date' => $this->futureDate(),
                'duration_minutes' => 60,
            ]))
            ->assertOk()
            ->assertJsonFragment(['start_time' => '09:00', 'end_time' => '10:00', 'available' => false])
            ->assertJsonFragment(['start_time' => '10:00', 'end_time' => '11:00', 'available' => true]);

        $this->actingAs($owner)
            ->post(route('owner.bookings.store'), $this->bookingData($resource))
            ->assertSessionHasErrors('start_time');

        $this->assertDatabaseCount('bookings', 0);

        $this->actingAs($owner)
            ->get(route('owner.bookings.index', ['date' => $this->futureDate()]))
            ->assertOk()
            ->assertSee('Court resurfacing');
    }

    public function test_reason_is_required_and_overlapping_blocks_are_rejected(): void
    {
        [$owner, $resource] = $this->bookingSetup();

        $this->actingAs($owner)
            ->post(route('owner.booking-blocks.store'), [
                ...$this->blockData($resource),
                'reason' => '',
            ])->assertSessionHasErrors('reason');

        $this->actingAs($owner)
            ->post(route('owner.booking-blocks.store'), $this->blockData($resource))
            ->assertRedirect();

        $this->actingAs($owner)
            ->post(route('owner.booking-blocks.store'), [
                ...$this->blockData($resource),
                'start_time' => '09:30',
                'end_time' => '10:30',
                'reason' => 'Private event setup',
            ])->assertSessionHasErrors('start_time');

        $this->assertDatabaseCount('court_availability_blocks', 1);
    }

    public function test_existing_reservation_is_not_replaced_by_a_court_block(): void
    {
        [$owner, $resource] = $this->bookingSetup();

        $this->actingAs($owner)
            ->post(route('owner.bookings.store'), $this->bookingData($resource))
            ->assertRedirect();

        $this->actingAs($owner)
            ->post(route('owner.booking-blocks.store'), $this->blockData($resource))
            ->assertSessionHasErrors('start_time');

        $this->assertDatabaseCount('court_availability_blocks', 0);
        $this->assertSame(BookingStatus::Confirmed, Booking::query()->firstOrFail()->status);
    }

    public function test_all_day_block_disables_every_open_slot_and_can_be_reopened(): void
    {
        [$owner, $resource] = $this->bookingSetup();

        $this->actingAs($owner)
            ->post(route('owner.booking-blocks.store'), [
                ...$this->blockData($resource),
                'is_all_day' => true,
                'start_time' => null,
                'end_time' => null,
                'reason' => 'Venue-wide maintenance day',
            ])->assertRedirect();

        $block = CourtAvailabilityBlock::query()->firstOrFail();
        $this->assertTrue($block->is_all_day);
        $this->assertEquals(24 * 60, $block->starts_at->diffInMinutes($block->ends_at));

        $slots = $this->actingAs($owner)
            ->getJson(route('owner.bookings.availability', [
                'resource_id' => $resource->getKey(),
                'date' => $this->futureDate(),
                'duration_minutes' => 60,
            ]))
            ->assertOk()
            ->json('slots');

        $this->assertNotEmpty($slots);
        $this->assertFalse(collect($slots)->contains(fn (array $slot) => $slot['available']));

        $this->actingAs($owner)
            ->delete(route('owner.booking-blocks.destroy', $block))
            ->assertRedirect();

        $this->assertNotNull($block->refresh()->cancelled_at);

        $this->actingAs($owner)
            ->post(route('owner.bookings.store'), $this->bookingData($resource))
            ->assertRedirect();
    }

    public function test_owner_can_create_a_weekly_recurring_block(): void
    {
        [$owner, $resource] = $this->bookingSetup();
        $repeatUntil = now('Asia/Manila')->addDays(21)->toDateString();

        $this->actingAs($owner)
            ->post(route('owner.booking-blocks.store'), [
                ...$this->blockData($resource),
                'repeat' => 'weekly',
                'repeat_until' => $repeatUntil,
                'reason' => 'Weekly coaching clinic',
            ])->assertRedirect();

        $blocks = CourtAvailabilityBlock::query()->orderBy('starts_at')->get();
        $this->assertCount(3, $blocks);
        $this->assertNotNull($blocks->first()->series_token);
        $this->assertCount(1, $blocks->pluck('series_token')->unique());
        $this->assertSame(
            [
                now('Asia/Manila')->addDays(7)->toDateString(),
                now('Asia/Manila')->addDays(14)->toDateString(),
                now('Asia/Manila')->addDays(21)->toDateString(),
            ],
            $blocks->map(fn (CourtAvailabilityBlock $block) => $block->starts_at
                ->setTimezone($block->timezone)
                ->toDateString())->all(),
        );
    }

    public function test_court_blocks_are_isolated_to_the_current_organization(): void
    {
        [$ownerA, $resourceA] = $this->bookingSetup();
        [$ownerB, $resourceB] = $this->bookingSetup();

        $this->actingAs($ownerA)
            ->post(route('owner.booking-blocks.store'), $this->blockData($resourceA))
            ->assertRedirect();
        $blockA = CourtAvailabilityBlock::query()->firstOrFail();

        $this->actingAs($ownerB)
            ->post(route('owner.booking-blocks.store'), $this->blockData($resourceA))
            ->assertNotFound();
        $this->actingAs($ownerB)
            ->delete(route('owner.booking-blocks.destroy', $blockA))
            ->assertNotFound();
        $this->actingAs($ownerB)
            ->post(route('owner.booking-blocks.store'), $this->blockData($resourceB))
            ->assertRedirect();

        $this->assertNull($blockA->refresh()->cancelled_at);
        $this->assertDatabaseCount('court_availability_blocks', 2);
    }

    /** @return array{User, CourtResource} */
    private function bookingSetup(): array
    {
        $owner = User::factory()->create();
        $organization = Organization::factory()->create(['timezone' => 'Asia/Manila']);
        Membership::factory()->owner()->for($owner)->for($organization)->create();
        $venue = Venue::factory()->for($organization)->create();
        $sport = Sport::factory()->create();
        $venue->sports()->attach($sport);
        $resource = CourtResource::factory()->for($venue)->for($sport)->create([
            'booking_increment_minutes' => 60,
            'is_active' => true,
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

    /** @return array<string, mixed> */
    private function blockData(CourtResource $resource): array
    {
        return [
            'resource_id' => $resource->getKey(),
            'block_date' => $this->futureDate(),
            'is_all_day' => false,
            'start_time' => '09:00',
            'end_time' => '10:00',
            'reason' => 'Court resurfacing',
            'repeat' => 'none',
            'repeat_until' => null,
        ];
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
            'customer_phone' => null,
            'notes' => null,
        ];
    }

    private function futureDate(): string
    {
        return now('Asia/Manila')->addDays(7)->toDateString();
    }
}
