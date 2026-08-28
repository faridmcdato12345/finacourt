<?php

namespace Database\Factories;

use App\Models\MarketingPreference;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MarketingPreference> */
class MarketingPreferenceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'marketing_opt_in' => false,
            'in_app_marketing_enabled' => false,
        ];
    }

    public function optedIn(): static
    {
        return $this->state(fn () => [
            'marketing_opt_in' => true,
            'in_app_marketing_enabled' => true,
            'opted_in_at' => now(),
            'opted_out_at' => null,
            'unsubscribed_at' => null,
        ]);
    }

    public function optedOut(): static
    {
        return $this->state(fn () => [
            'marketing_opt_in' => false,
            'in_app_marketing_enabled' => false,
            'opted_out_at' => now(),
            'unsubscribed_at' => now(),
        ]);
    }
}
