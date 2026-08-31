<?php

namespace App\Google\BusinessProfile;

use App\Models\GoogleBusinessProfileAudit;
use App\Models\GoogleBusinessProfileConnection;
use App\Models\User;
use App\Models\Venue;

class GoogleBusinessProfileAuditRecorder
{
    /** @param array<string, mixed> $context */
    public function record(
        Venue $venue,
        string $eventType,
        ?User $actor = null,
        ?GoogleBusinessProfileConnection $connection = null,
        array $context = [],
    ): GoogleBusinessProfileAudit {
        return GoogleBusinessProfileAudit::query()->create([
            'organization_id' => $venue->organization_id,
            'venue_id' => $venue->getKey(),
            'connection_id' => $connection?->getKey(),
            'actor_user_id' => $actor?->getKey(),
            'event_type' => $eventType,
            'context' => $context === [] ? null : $context,
            'occurred_at' => now('UTC'),
        ]);
    }
}
