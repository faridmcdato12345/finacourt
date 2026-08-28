<?php

namespace Database\Factories;

use App\Enums\PromotionDiscountType;
use App\Enums\PromotionGoal;
use App\Enums\PromotionStatus;
use App\Enums\PromotionType;
use App\Models\Promotion;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Promotion> */
class PromotionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'venue_id' => Venue::factory(),
            'campaign_token' => 'DEAL-'.Str::upper(Str::random(20)),
            'title' => fake()->sentence(4),
            'description' => fake()->sentence(),
            'promotion_type' => PromotionType::Deal,
            'goal' => PromotionGoal::FillEmptySlots,
            'status' => PromotionStatus::Active,
            'discount_type' => PromotionDiscountType::Percentage,
            'discount_value' => '20.00',
            'starts_on' => now()->subDay()->toDateString(),
            'ends_on' => now()->addMonth()->toDateString(),
            'targets_specific_slots' => false,
            'days_of_week' => null,
            'starts_at_time' => null,
            'ends_at_time' => null,
            'is_active' => true,
            'is_public' => true,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Promotion $promotion): void {
            $promotion->organization_id ??= $promotion->venue->organization_id;
        });
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'status' => PromotionStatus::Draft,
            'is_active' => false,
        ]);
    }

    public function paused(): static
    {
        return $this->state(fn () => [
            'status' => PromotionStatus::Paused,
            'is_active' => false,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'starts_on' => now()->subMonth()->toDateString(),
            'ends_on' => now()->subDay()->toDateString(),
        ]);
    }
}
