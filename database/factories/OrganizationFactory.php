<?php

namespace Database\Factories;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Organization> */
class OrganizationFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->company().' Courts';

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'timezone' => 'Asia/Manila',
        ];
    }
}
