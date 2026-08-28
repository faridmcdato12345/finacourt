<?php

namespace Database\Factories;

use App\Models\OperatingHour;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<OperatingHour> */
class OperatingHourFactory extends Factory
{
    public function definition(): array
    {
        return [
            'venue_id' => Venue::factory(),
            'day_of_week' => fake()->numberBetween(0, 6),
            'is_closed' => false,
            'opens_at' => '08:00',
            'closes_at' => '22:00',
        ];
    }

    public function closed(): static
    {
        return $this->state(fn () => [
            'is_closed' => true,
            'opens_at' => null,
            'closes_at' => null,
        ]);
    }
}
