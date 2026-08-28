<?php

namespace Database\Factories;

use App\Enums\CustomerLifecycle;
use App\Models\ReactivationCampaign;
use App\Models\ReactivationCampaignRecipient;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<ReactivationCampaignRecipient> */
class ReactivationCampaignRecipientFactory extends Factory
{
    public function definition(): array
    {
        return [
            'reactivation_campaign_id' => ReactivationCampaign::factory(),
            'user_id' => User::factory(),
            'click_token' => Str::lower(Str::random(40)),
            'lifecycle' => CustomerLifecycle::Inactive,
            'last_booking_at' => now()->subDays(30),
        ];
    }
}
