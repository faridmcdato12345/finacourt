<?php

namespace Database\Factories;

use App\Enums\PlatformServiceFeeType;
use App\Models\PlatformServiceFeeRule;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PlatformServiceFeeRule> */
class PlatformServiceFeeRuleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'FinACourt service fee',
            'fee_type' => PlatformServiceFeeType::Percentage,
            'percentage_basis_points' => 500,
            'fixed_amount' => null,
            'minimum_fee_amount' => '0.00',
            'maximum_fee_amount' => null,
            'currency' => 'PHP',
            'is_active' => true,
            'starts_at' => null,
            'ends_at' => null,
            'deactivated_at' => null,
            'created_by_user_id' => User::factory(),
        ];
    }

    public function fixed(string $amount = '25.00'): static
    {
        return $this->state(fn () => [
            'fee_type' => PlatformServiceFeeType::Fixed,
            'percentage_basis_points' => null,
            'fixed_amount' => $amount,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'is_active' => false,
            'deactivated_at' => now(),
        ]);
    }
}
