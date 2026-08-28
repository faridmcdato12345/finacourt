<?php

namespace Database\Factories;

use App\Enums\CommissionCalculation;
use App\Enums\CommissionRuleTrigger;
use App\Models\CommissionRule;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CommissionRule> */
class CommissionRuleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Activation milestone',
            'trigger' => CommissionRuleTrigger::VenueActivation,
            'calculation' => CommissionCalculation::Fixed,
            'fixed_amount' => '500.00',
            'currency' => 'PHP',
            'is_active' => true,
            'created_by_user_id' => User::factory()->platformAdmin(),
        ];
    }
}
