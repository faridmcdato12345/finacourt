<?php

namespace Database\Factories;

use App\Enums\LeadConflictStatus;
use App\Enums\SalesLeadStatus;
use App\Models\SalesLead;
use App\Models\SalesPartnerProfile;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<SalesLead> */
class SalesLeadFactory extends Factory
{
    public function definition(): array
    {
        $business = fake()->company();
        $contact = fake()->unique()->safeEmail();
        $city = fake()->city();

        return [
            'sales_partner_profile_id' => SalesPartnerProfile::factory(),
            'business_name' => $business,
            'contact_person' => fake()->name(),
            'contact_method' => 'email',
            'contact_value' => $contact,
            'dedupe_hash' => hash('sha256', Str::lower($business).'|'.Str::lower($contact)),
            'city' => $city,
            'lead_source' => 'field_outreach',
            'status' => SalesLeadStatus::New,
            'conflict_status' => LeadConflictStatus::Clear,
            'protection_started_at' => now('UTC'),
            'protection_expires_at' => now('UTC')->addDays(60),
            'status_changed_at' => now('UTC'),
        ];
    }
}
