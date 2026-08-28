<?php

namespace Database\Factories;

use App\Enums\ReactivationCampaignStatus;
use App\Enums\ReactivationSegment;
use App\Models\Organization;
use App\Models\ReactivationCampaign;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<ReactivationCampaign> */
class ReactivationCampaignFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'venue_id' => Venue::factory(),
            'created_by_user_id' => User::factory(),
            'campaign_token' => 'RETURN-'.Str::upper(Str::random(20)),
            'title' => fake()->sentence(4),
            'message' => fake()->sentence(12),
            'segment' => ReactivationSegment::Inactive30,
            'channel' => 'in_app',
            'status' => ReactivationCampaignStatus::Draft,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (ReactivationCampaign $campaign): void {
            $campaign->organization_id = $campaign->venue->organization_id;
        });
    }
}
