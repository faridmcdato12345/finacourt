<?php

namespace Database\Factories;

use App\Enums\DirectoryListingStatus;
use App\Enums\DirectorySourceType;
use App\Models\VenueDirectoryListing;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<VenueDirectoryListing> */
class VenueDirectoryListingFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->company().' Sports Facility';
        $address = fake()->streetAddress();
        $city = fake()->city();
        $province = fake()->state();

        return [
            'created_by_user_id' => null,
            'verified_by_user_id' => null,
            'rights_confirmed_by_user_id' => null,
            'claimed_venue_id' => null,
            'public_id' => (string) Str::ulid(),
            'directory_key' => hash('sha256', Str::lower("{$name}|{$address}|{$city}")),
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'status' => DirectoryListingStatus::Draft,
            'name' => $name,
            'description' => 'Original directory summary based on the cited public source.',
            'address' => $address,
            'city' => $city,
            'city_slug' => Str::slug($city),
            'province' => $province,
            'province_slug' => Str::slug($province),
            'country' => 'Philippines',
            'latitude' => null,
            'longitude' => null,
            'coordinates_verified_at' => null,
            'phone' => null,
            'email' => null,
            'website' => null,
            'source_type' => DirectorySourceType::OfficialWebsite,
            'source_url' => fake()->unique()->url(),
            'source_reference' => 'Factory-only test reference',
            'verification_notes' => null,
            'rights_confirmed_at' => now(),
            'last_verified_at' => null,
            'closed_at' => null,
            'claimed_at' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => [
            'status' => DirectoryListingStatus::Published,
            'last_verified_at' => now(),
        ]);
    }
}
