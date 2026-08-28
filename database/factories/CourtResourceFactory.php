<?php

namespace Database\Factories;

use App\Enums\ResourceSetting;
use App\Enums\ResourceType;
use App\Models\CourtResource;
use App\Models\Sport;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CourtResource> */
class CourtResourceFactory extends Factory
{
    public function configure(): static
    {
        return $this->afterCreating(function (CourtResource $resource): void {
            $resource->venue->sports()->syncWithoutDetaching([$resource->sport_id]);
        });
    }

    public function definition(): array
    {
        return [
            'venue_id' => Venue::factory(),
            'sport_id' => Sport::factory(),
            'name' => 'Court '.fake()->unique()->numberBetween(1, 9999),
            'resource_type' => ResourceType::Court,
            'setting' => fake()->randomElement(ResourceSetting::cases()),
            'is_active' => true,
            'base_hourly_rate' => fake()->randomFloat(2, 200, 2500),
            'currency' => 'PHP',
            'booking_increment_minutes' => fake()->randomElement([30, 60, 90]),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
