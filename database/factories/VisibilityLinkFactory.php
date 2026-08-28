<?php

namespace Database\Factories;

use App\Enums\VisibilityLinkDestination;
use App\Models\Venue;
use App\Models\VisibilityLink;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<VisibilityLink> */
class VisibilityLinkFactory extends Factory
{
    public function definition(): array
    {
        return [
            'venue_id' => Venue::factory(),
            'organization_id' => fn (array $attributes) => Venue::query()->findOrFail($attributes['venue_id'])->organization_id,
            'destination' => VisibilityLinkDestination::Venue,
            'link_key' => hash('sha256', fake()->uuid()),
            'token' => (string) Str::ulid(),
            'is_active' => true,
        ];
    }
}
