<?php

namespace Database\Factories;

use App\Enums\DirectoryClaimStatus;
use App\Enums\VenueClaimProofStatus;
use App\Models\Organization;
use App\Models\User;
use App\Models\VenueClaimRequest;
use App\Models\VenueDirectoryListing;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<VenueClaimRequest> */
class VenueClaimRequestFactory extends Factory
{
    public function definition(): array
    {
        return [
            'venue_directory_listing_id' => VenueDirectoryListing::factory()->published(),
            'requester_user_id' => User::factory(),
            'organization_id' => Organization::factory(),
            'reviewed_by_user_id' => null,
            'approved_venue_id' => null,
            'status' => DirectoryClaimStatus::Pending,
            'proof_status' => VenueClaimProofStatus::Pending,
            'active_claim_key' => hash('sha256', fake()->uuid()),
            'relationship_to_venue' => 'owner',
            'verification_contact' => fake()->safeEmail(),
            'evidence_details' => 'I operate this venue and can provide registration and utility records for review.',
            'review_notes' => null,
            'reviewed_at' => null,
        ];
    }
}
