<?php

namespace App\Directory;

use App\Models\User;
use App\Models\VenueClaimRequest;
use App\Models\VenueDirectoryAudit as AuditModel;
use App\Models\VenueDirectoryListing;

class VenueDirectoryAudit
{
    /** @param array<string, mixed> $changes */
    public function record(
        VenueDirectoryListing $listing,
        string $eventType,
        ?User $actor = null,
        ?VenueClaimRequest $claim = null,
        array $changes = [],
    ): AuditModel {
        return $listing->audits()->create([
            'venue_claim_request_id' => $claim?->getKey(),
            'actor_user_id' => $actor?->getKey(),
            'event_type' => $eventType,
            'changes' => $changes === [] ? null : $changes,
            'occurred_at' => now('UTC'),
        ]);
    }
}
