<?php

namespace Tests\Feature;

use App\Models\Membership;
use App\Models\OperatingHour;
use App\Models\Organization;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperatingHoursTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_save_a_complete_week_of_operating_hours(): void
    {
        [$owner, $venue] = $this->ownerAndVenue();
        $hours = $this->validHours();
        $hours[0] = ['day_of_week' => 0, 'is_closed' => true, 'opens_at' => null, 'closes_at' => null];

        $this->actingAs($owner)
            ->put(route('owner.venues.hours.update', $venue), ['hours' => $hours])
            ->assertRedirect(route('owner.venues.show', $venue));

        $this->assertCount(7, $venue->operatingHours()->get());
        $this->assertDatabaseHas('operating_hours', [
            'venue_id' => $venue->getKey(),
            'day_of_week' => 0,
            'is_closed' => true,
            'opens_at' => null,
            'closes_at' => null,
        ]);
        $this->assertDatabaseHas('operating_hours', [
            'venue_id' => $venue->getKey(),
            'day_of_week' => 1,
            'opens_at' => '08:00:00',
            'closes_at' => '22:00:00',
        ]);
    }

    public function test_operating_hours_require_each_weekday_exactly_once(): void
    {
        [$owner, $venue] = $this->ownerAndVenue();
        $hours = $this->validHours();
        $hours[6]['day_of_week'] = 5;

        $this->actingAs($owner)
            ->put(route('owner.venues.hours.update', $venue), ['hours' => $hours])
            ->assertSessionHasErrors(['hours', 'hours.6.day_of_week']);

        $this->assertDatabaseCount('operating_hours', 0);
    }

    public function test_open_day_requires_times_with_closing_later_than_opening(): void
    {
        [$owner, $venue] = $this->ownerAndVenue();
        $hours = $this->validHours();
        $hours[2]['opens_at'] = '22:00';
        $hours[2]['closes_at'] = '08:00';
        $hours[3]['opens_at'] = null;

        $this->actingAs($owner)
            ->put(route('owner.venues.hours.update', $venue), ['hours' => $hours])
            ->assertSessionHasErrors([
                'hours.2.closes_at',
                'hours.3.opens_at',
            ]);

        $this->assertDatabaseCount('operating_hours', 0);
    }

    public function test_tenant_cannot_edit_another_tenants_operating_hours(): void
    {
        [$owner] = $this->ownerAndVenue();
        $otherVenue = Venue::factory()->create();

        $this->actingAs($owner)
            ->get(route('owner.venues.hours.edit', $otherVenue))
            ->assertForbidden();
        $this->actingAs($owner)
            ->put(route('owner.venues.hours.update', $otherVenue), ['hours' => $this->validHours()])
            ->assertForbidden();

        $this->assertSame(0, OperatingHour::query()->where('venue_id', $otherVenue->getKey())->count());
    }

    /** @return array{User, Venue} */
    private function ownerAndVenue(): array
    {
        $owner = User::factory()->create();
        $organization = Organization::factory()->create();
        Membership::factory()->owner()->for($owner)->for($organization)->create();
        $venue = Venue::factory()->for($organization)->create();

        return [$owner, $venue];
    }

    /** @return array<int, array{day_of_week: int, is_closed: bool, opens_at: string, closes_at: string}> */
    private function validHours(): array
    {
        return array_map(fn (int $day) => [
            'day_of_week' => $day,
            'is_closed' => false,
            'opens_at' => '08:00',
            'closes_at' => '22:00',
        ], range(0, 6));
    }
}
