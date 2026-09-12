<?php

namespace App\Visibility;

use App\Enums\AcquisitionSource;
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
        AcquisitionSource $source = AcquisitionSource::QrCode,
    ): VisibilityLink {
        if (! Venue::query()->marketplace()->whereKey($venue->getKey())->exists()) {
            throw ValidationException::withMessages([
                'destination' => 'Show the venue to players and keep at least one court bookable before creating a public QR link.',
            ]);
        }

        if ($destination === VisibilityLinkDestination::Promotion && $promotion === null) {
            throw ValidationException::withMessages([
                'promotion_id' => 'Choose a deal for this QR code.',
            ]);
        }

        if ($destination !== VisibilityLinkDestination::Promotion && $promotion !== null) {
            throw ValidationException::withMessages([
                'promotion_id' => 'A deal can only be used with a deal QR code.',
            ]);
        }

        if ($promotion !== null && (
            $promotion->organization_id !== $venue->organization_id
            || $promotion->venue_id !== $venue->getKey()
        )) {
            throw ValidationException::withMessages([
                'promotion_id' => 'The selected deal does not belong to this venue.',
            ]);
        }

        if (! $source->canUseTrackedVenueLink()) {
            throw ValidationException::withMessages([
                'source' => 'Choose a supported channel for this tracked venue link.',
            ]);
        }

        $keyParts = [
            'venue',
            $venue->getKey(),
            $destination->value,
            $promotion?->getKey() ?? 0,
        ];

        // Preserve the original deterministic key for existing QR links.
        if ($source !== AcquisitionSource::QrCode) {
            $keyParts[] = $source->value;
        }

        $linkKey = hash('sha256', implode(':', $keyParts));

        return VisibilityLink::query()->firstOrCreate(
            ['link_key' => $linkKey],
            [
                'organization_id' => $venue->organization_id,
                'venue_id' => $venue->getKey(),
                'promotion_id' => $promotion?->getKey(),
                'created_by_user_id' => $creator->getKey(),
                'destination' => $destination,
                'acquisition_source' => $source,
                'token' => (string) Str::ulid(),
                'is_active' => true,
            ],
        );
    }
}
