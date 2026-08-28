<?php

namespace Tests\Feature;

use App\Enums\OrganizationPermission;
use App\Models\Amenity;
use App\Models\CourtResource;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Sport;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class VenueManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_users_cannot_access_venue_management(): void
    {
        $this->get(route('owner.venues.index'))->assertRedirect(route('login'));
        $this->get(route('owner.venues.create'))->assertRedirect(route('login'));
    }

    public function test_owner_can_create_a_venue_with_catalog_relationships_and_default_hours(): void
    {
        [$owner, $organization] = $this->ownerWithOrganization();
        $otherOrganization = Organization::factory()->create();
        $sport = Sport::factory()->create();
        $amenities = Amenity::factory()->count(2)->create();

        $response = $this->actingAs($owner)->post(route('owner.venues.store'), [
            ...$this->venueData($sport, $amenities->modelKeys()),
            'organization_id' => $otherOrganization->getKey(),
        ]);

        $venue = Venue::query()->firstOrFail();

        $response->assertRedirect(route('owner.venues.show', $venue));
        $this->assertSame($organization->getKey(), $venue->organization_id);
        $this->assertSame('central-sports-hub', $venue->slug);
        $this->assertNotNull($venue->claimed_at);
        $this->assertFalse($venue->is_published);
        $this->assertEqualsCanonicalizing([$sport->getKey()], $venue->sports()->pluck('sports.id')->all());
        $this->assertEqualsCanonicalizing($amenities->modelKeys(), $venue->amenities()->pluck('amenities.id')->all());
        $this->assertCount(7, $venue->operatingHours);
    }

    public function test_venue_index_only_returns_the_current_tenants_venues(): void
    {
        [$owner, $organization] = $this->ownerWithOrganization();
        $ownVenue = Venue::factory()->for($organization)->create();
        $otherVenue = Venue::factory()->create();

        $this->actingAs($owner)
            ->get(route('owner.venues.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Owner/Venues/Index')
                ->has('venues', 1)
                ->where('venues.0.id', $ownVenue->getKey())
                ->missing('venues.1'));

        $this->assertNotSame($ownVenue->organization_id, $otherVenue->organization_id);
    }

    public function test_owner_can_update_publish_and_delete_their_venue(): void
    {
        [$owner, $organization] = $this->ownerWithOrganization();
        $venue = Venue::factory()->for($organization)->create();
        $oldSport = Sport::factory()->create();
        $newSport = Sport::factory()->create();
        $amenity = Amenity::factory()->create();
        $venue->sports()->attach($oldSport);

        $this->actingAs($owner)->put(route('owner.venues.update', $venue), [
            ...$this->venueData($newSport, [$amenity->getKey()]),
            'name' => 'Updated Venue',
            'slug' => 'updated-venue',
            'is_published' => true,
        ])->assertRedirect(route('owner.venues.show', $venue));

        $venue->refresh();
        $this->assertSame('Updated Venue', $venue->name);
        $this->assertTrue($venue->is_published);
        $this->assertEqualsCanonicalizing([$newSport->getKey()], $venue->sports()->pluck('sports.id')->all());
        $this->assertEqualsCanonicalizing([$amenity->getKey()], $venue->amenities()->pluck('amenities.id')->all());

        $this->actingAs($owner)
            ->delete(route('owner.venues.destroy', $venue))
            ->assertRedirect(route('owner.venues.index'));

        $this->assertDatabaseMissing('venues', ['id' => $venue->getKey()]);
    }

    public function test_tenant_cannot_read_update_or_delete_another_tenants_venue(): void
    {
        [$owner] = $this->ownerWithOrganization();
        $otherVenue = Venue::factory()->create();
        $sport = Sport::factory()->create();

        $this->actingAs($owner)->get(route('owner.venues.show', $otherVenue))->assertForbidden();
        $this->actingAs($owner)->get(route('owner.venues.edit', $otherVenue))->assertForbidden();
        $this->actingAs($owner)
            ->put(route('owner.venues.update', $otherVenue), $this->venueData($sport))
            ->assertForbidden();
        $this->actingAs($owner)->delete(route('owner.venues.destroy', $otherVenue))->assertForbidden();
        $this->assertDatabaseHas('venues', ['id' => $otherVenue->getKey()]);
    }

    public function test_automatically_generated_venue_slugs_are_globally_unique(): void
    {
        [$owner] = $this->ownerWithOrganization();
        $sport = Sport::factory()->create();

        $this->actingAs($owner)->post(route('owner.venues.store'), $this->venueData($sport))->assertRedirect();
        $this->actingAs($owner)->post(route('owner.venues.store'), $this->venueData($sport))->assertRedirect();

        $this->assertDatabaseHas('venues', ['slug' => 'central-sports-hub']);
        $this->assertDatabaseHas('venues', ['slug' => 'central-sports-hub-2']);
    }

    public function test_explicit_duplicate_venue_slug_is_rejected(): void
    {
        [$owner, $organization] = $this->ownerWithOrganization();
        Venue::factory()->for($organization)->create(['slug' => 'taken-slug']);
        $sport = Sport::factory()->create();

        $this->actingAs($owner)
            ->post(route('owner.venues.store'), [
                ...$this->venueData($sport),
                'slug' => 'taken-slug',
            ])
            ->assertSessionHasErrors('slug');

        $this->assertSame(1, Venue::query()->count());
    }

    public function test_sport_used_by_a_resource_cannot_be_removed_from_venue(): void
    {
        [$owner, $organization] = $this->ownerWithOrganization();
        $venue = Venue::factory()->for($organization)->create();
        $usedSport = Sport::factory()->create();
        $otherSport = Sport::factory()->create();
        $venue->sports()->attach([$usedSport->getKey(), $otherSport->getKey()]);
        CourtResource::factory()->for($venue)->for($usedSport)->create();

        $this->actingAs($owner)
            ->put(route('owner.venues.update', $venue), $this->venueData($otherSport))
            ->assertSessionHasErrors('sports');

        $this->assertTrue($venue->sports()->whereKey($usedSport->getKey())->exists());
    }

    public function test_inventory_permission_is_required_for_staff(): void
    {
        $organization = Organization::factory()->create();
        $readOnlyStaff = User::factory()->create();
        $inventoryStaff = User::factory()->create();

        Membership::factory()->for($readOnlyStaff)->for($organization)
            ->withPermissions([OrganizationPermission::ViewDashboard])->create();
        Membership::factory()->for($inventoryStaff)->for($organization)
            ->withPermissions([
                OrganizationPermission::ViewDashboard,
                OrganizationPermission::ManageInventory,
            ])->create();

        $this->actingAs($readOnlyStaff)->get(route('owner.venues.index'))->assertForbidden();
        $this->actingAs($inventoryStaff)->get(route('owner.venues.index'))->assertOk();
    }

    public function test_platform_admin_is_explicitly_authorized_across_tenant_boundaries(): void
    {
        $contextOrganization = Organization::factory()->create();
        $otherVenue = Venue::factory()->create();
        $platformAdmin = User::factory()->create(['is_platform_admin' => true]);

        $this->actingAs($platformAdmin)
            ->withSession(['tenant.organization_id' => $contextOrganization->getKey()])
            ->get(route('owner.venues.show', $otherVenue))
            ->assertOk();
    }

    /** @return array{User, Organization} */
    private function ownerWithOrganization(): array
    {
        $owner = User::factory()->create();
        $organization = Organization::factory()->create();
        Membership::factory()->owner()->for($owner)->for($organization)->create();

        return [$owner, $organization];
    }

    /** @param array<int, int> $amenityIds */
    private function venueData(Sport $sport, array $amenityIds = []): array
    {
        return [
            'name' => 'Central Sports Hub',
            'slug' => null,
            'description' => 'A community sports facility.',
            'address' => '100 Main Street',
            'city' => 'Makati',
            'province' => 'Metro Manila',
            'latitude' => '14.5547000',
            'longitude' => '121.0244000',
            'phone' => '+63 2 8000 0000',
            'email' => 'venue@example.com',
            'website' => 'https://example.com',
            'is_published' => false,
            'sports' => [$sport->getKey()],
            'amenities' => $amenityIds,
        ];
    }
}
