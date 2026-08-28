<?php

namespace Database\Factories;

use App\Enums\CommissionEntryStatus;
use App\Models\CommissionEntry;
use App\Models\SalesPartnerProfile;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<CommissionEntry> */
class CommissionEntryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'sales_partner_profile_id' => SalesPartnerProfile::factory(),
            'source_type' => 'admin_adjustment',
            'source_reference' => null,
            'idempotency_key' => 'factory:'.Str::uuid(),
            'amount' => '100.00',
            'currency' => 'PHP',
            'status' => CommissionEntryStatus::Pending,
            'reason' => 'Test ledger entry',
        ];
    }
}
