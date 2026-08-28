<?php

namespace Tests\Feature;

use App\Models\Membership;
use App\Models\Organization;
use App\Models\PsgcLocation;
use App\Models\Sport;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PsgcVenueLocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_form_exposes_only_location_parents_with_bookable_localities(): void
    {
        [$owner] = $this->ownerWithOrganization();
        $this->seedLocationHierarchy();

        $this->actingAs($owner)
            ->get(route('owner.venues.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Owner/Venues/Create')
                ->has('locationParents', 2)
                ->where('locationParents.0.code', '1102400000')
                ->where('locationParents.1.code', '1206300000'));
    }

    public function test_authenticated_owner_can_load_cities_for_a_selected_province(): void
    {
        [$owner] = $this->ownerWithOrganization();
        $this->seedLocationHierarchy();

        $this->actingAs($owner)
            ->getJson(route('owner.location-options.cities', ['parent_code' => '1102400000']))
            ->assertOk()
            ->assertExactJson([
                'data' => [[
                    'code' => '1102403000',
                    'name' => 'City of Digos',
                    'level' => 'city',
                    'type' => 'component_city',
                ]],
            ]);
    }

    public function test_location_names_and_hierarchy_are_server_derived_from_psgc_codes(): void
    {
        [$owner, $organization] = $this->ownerWithOrganization();
        $this->seedLocationHierarchy();
        $sport = Sport::factory()->create();

        $this->actingAs($owner)->post(route('owner.venues.store'), [
            ...$this->venueData($sport),
            'city' => 'Tampered City',
            'province' => 'Tampered Province',
            'psgc_parent_code' => '1102400000',
            'psgc_city_municipality_code' => '1102403000',
            'organization_id' => Organization::factory()->create()->getKey(),
        ])->assertRedirect();

        $venue = Venue::query()->sole();
        $this->assertSame($organization->getKey(), $venue->organization_id);
        $this->assertSame('City of Digos', $venue->city);
        $this->assertSame('Davao del Sur', $venue->province);
        $this->assertSame('1100000000', $venue->psgc_region_code);
        $this->assertSame('1102400000', $venue->psgc_province_code);
        $this->assertSame('1102403000', $venue->psgc_city_municipality_code);
    }

    public function test_city_from_another_province_is_rejected(): void
    {
        [$owner] = $this->ownerWithOrganization();
        $this->seedLocationHierarchy();
        $sport = Sport::factory()->create();

        $this->actingAs($owner)
            ->post(route('owner.venues.store'), [
                ...$this->venueData($sport),
                'psgc_parent_code' => '1102400000',
                'psgc_city_municipality_code' => '1206301000',
            ])
            ->assertSessionHasErrors('psgc_city_municipality_code');

        $this->assertDatabaseCount('venues', 0);
    }

    public function test_location_options_require_an_authenticated_tenant_context(): void
    {
        $this->seedLocationHierarchy();

        $this->get(route('owner.location-options.cities', ['parent_code' => '1102400000']))
            ->assertRedirect(route('login'));
    }

    /** @return array{User, Organization} */
    private function ownerWithOrganization(): array
    {
        $owner = User::factory()->create();
        $organization = Organization::factory()->create();
        Membership::factory()->owner()->for($owner)->for($organization)->create();

        return [$owner, $organization];
    }

    private function seedLocationHierarchy(): void
    {
        foreach ([
            ['code' => '1100000000', 'parent_code' => null, 'name' => 'Region XI (Davao Region)', 'level' => 'region', 'type' => 'region'],
            ['code' => '1200000000', 'parent_code' => null, 'name' => 'Region XII (SOCCSKSARGEN)', 'level' => 'region', 'type' => 'region'],
            ['code' => '1102400000', 'parent_code' => '1100000000', 'name' => 'Davao del Sur', 'level' => 'province', 'type' => 'province'],
            ['code' => '1206300000', 'parent_code' => '1200000000', 'name' => 'South Cotabato', 'level' => 'province', 'type' => 'province'],
            ['code' => '1102403000', 'parent_code' => '1102400000', 'name' => 'City of Digos', 'level' => 'city', 'type' => 'component_city'],
            ['code' => '1206301000', 'parent_code' => '1206300000', 'name' => 'Banga', 'level' => 'municipality', 'type' => 'municipality'],
        ] as $location) {
            PsgcLocation::query()->create([
                ...$location,
                'source_version' => 'test',
            ]);
        }
    }

    /** @return array<string, mixed> */
    private function venueData(Sport $sport): array
    {
        return [
            'name' => 'PSGC Sports Hub',
            'slug' => null,
            'description' => null,
            'address' => '100 Main Street',
            'city' => null,
            'province' => null,
            'latitude' => null,
            'longitude' => null,
            'phone' => null,
            'email' => null,
            'website' => null,
            'is_published' => false,
            'sports' => [$sport->getKey()],
            'amenities' => [],
        ];
    }
}
