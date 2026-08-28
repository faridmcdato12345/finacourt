<?php

namespace Database\Factories;

use App\Models\Venue;
use App\Models\VenuePhoto;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<VenuePhoto> */
class VenuePhotoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'venue_id' => Venue::factory(),
            'storage_path' => 'venues/placeholders/'.fake()->uuid().'.jpg',
            'alt_text' => fake()->sentence(4),
            'sort_order' => 0,
            'is_primary' => false,
        ];
    }
}
