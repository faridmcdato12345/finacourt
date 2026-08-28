<?php

namespace Tests\Feature;

use App\Enums\ResourceSetting;
use App\Enums\ResourceType;
use App\Models\CourtResource;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Sport;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourtResourceManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_and_update_a_resource_with_base_pricing(): void
    {
        [$owner, $venue, $sport] = $this->ownerVenueAndSport();

        $this->actingAs($owner)
            ->post(route('owner.venues.resources.store', $venue), $this->resourceData($sport))
            ->assertRedirect(route('owner.venues.show', $venue));

        $resource = CourtResource::query()->firstOrFail();
        $this->assertSame($venue->getKey(), $resource->venue_id);
        $this->assertSame(ResourceType::Court, $resource->resource_type);
        $this->assertSame(ResourceSetting::Indoor, $resource->setting);
        $this->assertSame('650.00', $resource->base_hourly_rate);
        $this->assertSame('PHP', $resource->currency);
        $this->assertTrue($resource->is_active);

        $this->actingAs($owner)
            ->put(route('owner.venues.resources.update', [$venue, $resource]), [
                ...$this->resourceData($sport),
                'name' => 'Court A',
                'base_hourly_rate' => '725.50',
                'is_active' => false,
                'booking_increment_minutes' => 30,
            ])
            ->assertRedirect(route('owner.venues.show', $venue));

        $resource->refresh();
        $this->assertSame('725.50', $resource->base_hourly_rate);
        $this->assertFalse($resource->is_active);
        $this->assertSame(30, $resource->booking_increment_minutes);
    }

    public function test_tenant_cannot_manage_another_tenants_resources(): void
    {
        [$owner] = $this->ownerVenueAndSport();
        $otherVenue = Venue::factory()->create();
        $otherSport = Sport::factory()->create();
        $otherVenue->sports()->attach($otherSport);
        $resource = CourtResource::factory()->for($otherVenue)->for($otherSport)->create();

        $this->actingAs($owner)
            ->get(route('owner.venues.resources.edit', [$otherVenue, $resource]))
            ->assertForbidden();
        $this->actingAs($owner)
            ->post(route('owner.venues.resources.store', $otherVenue), $this->resourceData($otherSport))
            ->assertForbidden();
        $this->actingAs($owner)
            ->put(route('owner.venues.resources.update', [$otherVenue, $resource]), $this->resourceData($otherSport))
            ->assertForbidden();
        $this->actingAs($owner)
            ->delete(route('owner.venues.resources.destroy', [$otherVenue, $resource]))
            ->assertForbidden();
        $this->assertDatabaseHas('resources', ['id' => $resource->getKey()]);
    }

    public function test_resource_cannot_use_a_sport_not_offered_by_venue(): void
    {
        [$owner, $venue] = $this->ownerVenueAndSport();
        $unavailableSport = Sport::factory()->create();

        $this->actingAs($owner)
            ->post(route('owner.venues.resources.store', $venue), $this->resourceData($unavailableSport))
            ->assertSessionHasErrors('sport_id');

        $this->assertDatabaseCount('resources', 0);
    }

    public function test_resource_pricing_increment_and_enum_values_are_validated(): void
    {
        [$owner, $venue, $sport] = $this->ownerVenueAndSport();

        $this->actingAs($owner)
            ->post(route('owner.venues.resources.store', $venue), [
                ...$this->resourceData($sport),
                'base_hourly_rate' => '-1.999',
                'booking_increment_minutes' => 17,
                'resource_type' => 'unsupported',
                'setting' => 'somewhere',
            ])
            ->assertSessionHasErrors([
                'base_hourly_rate',
                'booking_increment_minutes',
                'resource_type',
                'setting',
            ]);
    }

    public function test_resource_names_are_unique_within_a_venue_but_reusable_elsewhere(): void
    {
        [$owner, $venue, $sport] = $this->ownerVenueAndSport();
        CourtResource::factory()->for($venue)->for($sport)->create(['name' => 'Court 1']);

        $this->actingAs($owner)
            ->post(route('owner.venues.resources.store', $venue), $this->resourceData($sport))
            ->assertSessionHasErrors('name');

        $otherVenue = Venue::factory()->create();
        CourtResource::factory()->for($otherVenue)->for($sport)->create(['name' => 'Court 1']);
        $this->assertSame(2, CourtResource::query()->where('name', 'Court 1')->count());
    }

    public function test_nested_route_cannot_associate_resource_with_a_different_venue(): void
    {
        [$owner, $venue] = $this->ownerVenueAndSport();
        $otherVenue = Venue::factory()->for($venue->organization)->create();
        $resource = CourtResource::factory()->for($otherVenue)->create();

        $this->actingAs($owner)
            ->get(route('owner.venues.resources.edit', [$venue, $resource]))
            ->assertNotFound();
    }

    /** @return array{User, Venue, Sport} */
    private function ownerVenueAndSport(): array
    {
        $owner = User::factory()->create();
        $organization = Organization::factory()->create();
        Membership::factory()->owner()->for($owner)->for($organization)->create();
        $venue = Venue::factory()->for($organization)->create();
        $sport = Sport::factory()->create();
        $venue->sports()->attach($sport);

        return [$owner, $venue, $sport];
    }

    private function resourceData(Sport $sport): array
    {
        return [
            'name' => 'Court 1',
            'sport_id' => $sport->getKey(),
            'resource_type' => ResourceType::Court->value,
            'setting' => ResourceSetting::Indoor->value,
            'is_active' => true,
            'base_hourly_rate' => '650.00',
            'booking_increment_minutes' => 60,
        ];
    }
}
