<?php

namespace Database\Factories;

use App\Models\Amenity;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Amenity> */
class AmenityFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true).' '.Str::upper(Str::random(3));

        return [
            'name' => Str::title($name),
            'slug' => Str::slug($name),
            'is_active' => true,
        ];
    }
}
