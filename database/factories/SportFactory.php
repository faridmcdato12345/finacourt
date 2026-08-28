<?php

namespace Database\Factories;

use App\Models\Sport;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Sport> */
class SportFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->randomElement([
            'Badminton', 'Basketball', 'Futsal', 'Padel', 'Pickleball', 'Squash', 'Tennis', 'Volleyball',
        ]).' '.Str::upper(Str::random(3));

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'is_active' => true,
        ];
    }
}
