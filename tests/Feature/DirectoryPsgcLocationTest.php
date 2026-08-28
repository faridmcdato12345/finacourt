<?php

namespace Tests\Feature;

use App\Models\PsgcLocation;
use App\Models\Sport;
use App\Models\User;
use App\Models\VenueDirectoryListing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DirectoryPsgcLocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_directory_form_uses_the_psgc_parent_and_child_catalog(): void
    {
        $this->seedLocationHierarchy();
        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($admin)
            ->get(route('platform.directory.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Platform/Directory/Create')
                ->where('locationParents.0.code', '1102400000')
                ->where('locationParents.0.label', 'Davao del Sur — Province'));

        $this->getJson(route('platform.location-options.cities', [
            'parent_code' => '1102400000',
        ]))
            ->assertOk()
            ->assertJsonPath('data.0.code', '1102403000')
            ->assertJsonPath('data.0.name', 'City of Digos');
    }

    public function test_directory_creation_derives_official_location_names_from_validated_codes(): void
    {
        $this->seedLocationHierarchy();
        $admin = User::factory()->platformAdmin()->create();
        $sport = Sport::factory()->create();
        $payload = $this->listingPayload($sport);
        $payload['city'] = 'Browser supplied city';
        $payload['province'] = 'Browser supplied province';

        $this->actingAs($admin)
            ->post(route('platform.directory.store'), $payload)
            ->assertRedirect();

        $listing = VenueDirectoryListing::query()->sole();
        $this->assertSame('City of Digos', $listing->city);
        $this->assertSame('Davao del Sur', $listing->province);
        $this->assertSame('1100000000', $listing->psgc_region_code);
        $this->assertSame('1102400000', $listing->psgc_province_code);
        $this->assertSame('1102403000', $listing->psgc_city_municipality_code);
    }

    public function test_directory_rejects_a_city_from_a_different_psgc_parent(): void
    {
        $this->seedLocationHierarchy();
        PsgcLocation::query()->create([
            'code' => '1300000000',
            'name' => 'National Capital Region',
            'level' => 'region',
            'type' => 'Region',
            'source_version' => 'test',
        ]);
        PsgcLocation::query()->create([
            'code' => '1380600000',
            'parent_code' => '1300000000',
            'name' => 'City of Makati',
            'level' => 'city',
            'type' => 'Highly Urbanized City',
            'source_version' => 'test',
        ]);
        $admin = User::factory()->platformAdmin()->create();
        $sport = Sport::factory()->create();
        $payload = $this->listingPayload($sport);
        $payload['psgc_city_municipality_code'] = '1380600000';

        $this->actingAs($admin)
            ->post(route('platform.directory.store'), $payload)
            ->assertSessionHasErrors('psgc_city_municipality_code');

        $this->assertSame(0, VenueDirectoryListing::query()->count());
    }

    public function test_international_directory_locations_keep_the_manual_fallback(): void
    {
        $this->seedLocationHierarchy();
        $admin = User::factory()->platformAdmin()->create();
        $sport = Sport::factory()->create();
        $payload = $this->listingPayload($sport);
        $payload['country'] = 'Singapore';
        $payload['province'] = 'Central Region';
        $payload['city'] = 'Singapore';
        unset($payload['psgc_parent_code'], $payload['psgc_city_municipality_code']);

        $this->actingAs($admin)
            ->post(route('platform.directory.store'), $payload)
            ->assertRedirect();

        $listing = VenueDirectoryListing::query()->sole();
        $this->assertSame('Singapore', $listing->city);
        $this->assertSame('Central Region', $listing->province);
        $this->assertNull($listing->psgc_region_code);
        $this->assertNull($listing->psgc_province_code);
        $this->assertNull($listing->psgc_city_municipality_code);
    }

    public function test_non_platform_users_cannot_read_the_directory_location_endpoint(): void
    {
        $this->seedLocationHierarchy();

        $this->actingAs(User::factory()->create())
            ->getJson(route('platform.location-options.cities', [
                'parent_code' => '1102400000',
            ]))
            ->assertForbidden();
    }

    private function seedLocationHierarchy(): void
    {
        PsgcLocation::query()->create([
            'code' => '1100000000',
            'name' => 'Region XI (Davao Region)',
            'level' => 'region',
            'type' => 'Region',
            'source_version' => 'test',
        ]);
        PsgcLocation::query()->create([
            'code' => '1102400000',
            'parent_code' => '1100000000',
            'name' => 'Davao del Sur',
            'level' => 'province',
            'type' => 'Province',
            'source_version' => 'test',
        ]);
        PsgcLocation::query()->create([
            'code' => '1102403000',
            'parent_code' => '1102400000',
            'name' => 'City of Digos',
            'level' => 'city',
            'type' => 'Component City',
            'source_version' => 'test',
        ]);
    }

    /** @return array<string, mixed> */
    private function listingPayload(Sport $sport): array
    {
        return [
            'name' => 'PSGC Directory Venue',
            'description' => 'An original factual summary written by the platform administrator.',
            'address' => '100 Public Road',
            'city' => '',
            'province' => '',
            'country' => 'Philippines',
            'psgc_parent_code' => '1102400000',
            'psgc_city_municipality_code' => '1102403000',
            'latitude' => '6.7499000',
            'longitude' => '125.3572000',
            'coordinates_verified' => true,
            'phone' => '+63 900 000 0000',
            'email' => 'public@example.com',
            'website' => 'https://venue.example.com',
            'source_type' => 'official_website',
            'source_url' => 'https://venue.example.com/contact',
            'source_reference' => 'Official contact page checked manually',
            'rights_confirmed' => true,
            'sports' => [$sport->getKey()],
        ];
    }
}
