<?php

namespace Database\Factories;

use App\Models\ExternalBookingDestination;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ExternalBookingDestination> */
class ExternalBookingDestinationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'venue_id' => Venue::factory(),
            'organization_id' => fn (array $attributes) => Venue::query()
                ->findOrFail($attributes['venue_id'])
                ->organization_id,
            'provider_name' => 'Current booking platform',
            'destination_url' => 'https://booking.example/venues/'.fake()->slug(),
            'is_active' => true,
        ];
    }
}
