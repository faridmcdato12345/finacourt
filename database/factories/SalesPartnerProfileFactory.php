<?php

namespace Database\Factories;

use App\Enums\SalesPartnerStatus;
use App\Models\SalesPartnerProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<SalesPartnerProfile> */
class SalesPartnerProfileFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'public_id' => (string) Str::ulid(),
            'referral_code' => 'REP-'.Str::upper(Str::random(10)),
            'status' => SalesPartnerStatus::Active,
            'activated_at' => now('UTC'),
        ];
    }

    public function suspended(): static
    {
        return $this->state(fn () => [
            'status' => SalesPartnerStatus::Suspended,
            'suspended_at' => now('UTC'),
            'suspension_reason' => 'Test suspension',
        ]);
    }
}
