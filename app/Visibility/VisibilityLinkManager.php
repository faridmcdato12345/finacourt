<?php

namespace App\Visibility;

use App\Enums\VisibilityLinkDestination;
use App\Models\Promotion;
use App\Models\User;
use App\Models\Venue;
use App\Models\VisibilityLink;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class VisibilityLinkManager
{
    public function create(
        Venue $venue,
        VisibilityLinkDestination $destination,
        ?Promotion $promotion,
        User $creator,
    ): VisibilityLink {
        if (! Venue::query()->marketplace()->whereKey($venue->getKey())->exists()) {
            throw ValidationException::withMessages([
                'destination' => 'Publish the venue with at least one active court before creating a public QR link.',
            ]);
        }

        if ($destination === VisibilityLinkDestination::Promotion && $promotion === null) {
            throw ValidationException::withMessages([
                'promotion_id' => 'Choose a promotion for this QR destination.',
            ]);
        }

        if ($destination !== VisibilityLinkDestination::Promotion && $promotion !== null) {
            throw ValidationException::withMessages([
                'promotion_id' => 'A promotion is only valid for a promotion QR destination.',
            ]);
        }

        if ($promotion !== null && (
            $promotion->organization_id !== $venue->organization_id
            || $promotion->venue_id !== $venue->getKey()
        )) {
            throw ValidationException::withMessages([
                'promotion_id' => 'The selected promotion does not belong to this venue.',
            ]);
        }

        $linkKey = hash('sha256', implode(':', [
            'venue',
            $venue->getKey(),
            $destination->value,
            $promotion?->getKey() ?? 0,
        ]));

        return VisibilityLink::query()->firstOrCreate(
            ['link_key' => $linkKey],
            [
                'organization_id' => $venue->organization_id,
                'venue_id' => $venue->getKey(),
                'promotion_id' => $promotion?->getKey(),
                'created_by_user_id' => $creator->getKey(),
                'destination' => $destination,
                'token' => (string) Str::ulid(),
                'is_active' => true,
            ],
        );
    }
}
