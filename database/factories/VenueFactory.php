<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Venue> */
class VenueFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->company().' Sports Center';
        $city = fake()->city();
        $province = fake()->state();

        return [
            'organization_id' => Organization::factory(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'description' => fake()->paragraph(),
            'address' => fake()->streetAddress(),
            'city' => $city,
            'city_slug' => Str::slug($city),
            'province' => $province,
            'province_slug' => Str::slug($province),
            'latitude' => fake()->optional()->latitude(),
            'longitude' => fake()->optional()->longitude(),
            'phone' => fake()->optional()->phoneNumber(),
            'email' => fake()->optional()->companyEmail(),
            'website' => fake()->optional()->url(),
            'is_published' => false,
            'claimed_at' => now(),
            'verified_at' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => ['is_published' => true]);
    }

    public function verified(): static
    {
        return $this->state(fn () => ['verified_at' => now()]);
    }
}
