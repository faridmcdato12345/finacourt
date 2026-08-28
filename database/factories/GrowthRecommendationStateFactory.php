<?php

namespace Database\Factories;

use App\Enums\GrowthRecommendationStateStatus;
use App\Enums\GrowthRecommendationType;
use App\Models\GrowthRecommendationState;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<GrowthRecommendationState> */
class GrowthRecommendationStateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'venue_id' => null,
            'acted_by_user_id' => User::factory(),
            'recommendation_key' => hash('sha256', fake()->uuid()),
            'recommendation_type' => GrowthRecommendationType::EmptyInventory,
            'status' => GrowthRecommendationStateStatus::Dismissed,
            'snoozed_until' => null,
        ];
    }
}
