<?php

namespace App\Visibility;

use App\Enums\AcquisitionSource;
use App\Enums\VisibilityLinkDestination;
use App\Models\ExternalBookingDestination;
use App\Models\Promotion;
use App\Models\User;
use App\Models\Venue;
use App\Models\VisibilityLink;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class VisibilityLinkManager
{
    /** @return array<int, AcquisitionSource> */
    public static function externalSources(): array
    {
        return [
            AcquisitionSource::Facebook,
            AcquisitionSource::GoogleMaps,
            AcquisitionSource::Instagram,
            AcquisitionSource::QrCode,
            AcquisitionSource::SharedLink,
            AcquisitionSource::Referral,
            AcquisitionSource::TikTok,
        ];
    }

    public function create(
        Venue $venue,
        VisibilityLinkDestination $destination,
        ?Promotion $promotion,
        User $creator,
        AcquisitionSource $source = AcquisitionSource::QrCode,
    ): VisibilityLink {
        if ($destination === VisibilityLinkDestination::ExternalBooking) {
            throw ValidationException::withMessages([
                'destination' => 'Create external booking links from the Booking Links page.',
            ]);
        }

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

    public function createExternal(
        Venue $venue,
        ExternalBookingDestination $destination,
        User $creator,
        AcquisitionSource $source,
        ?string $label = null,
        ?string $campaign = null,
    ): VisibilityLink {
        if ($destination->organization_id !== $venue->organization_id
            || $destination->venue_id !== $venue->getKey()) {
            throw ValidationException::withMessages([
                'destination' => 'The booking destination does not belong to this venue.',
            ]);
        }

        if (! $destination->is_active) {
            throw ValidationException::withMessages([
                'destination' => 'Turn on the current booking destination before creating links.',
            ]);
        }

        if (! in_array($source, self::externalSources(), true)) {
            throw ValidationException::withMessages([
                'source' => 'Choose a supported source for this booking link.',
            ]);
        }

        $campaign = filled($campaign) ? Str::lower(trim((string) $campaign)) : null;
        $linkKey = hash('sha256', implode(':', [
            'venue',
            $venue->getKey(),
            VisibilityLinkDestination::ExternalBooking->value,
            $destination->getKey(),
            $source->value,
            $campaign ?? 'default',
        ]));
        $link = VisibilityLink::query()->withTrashed()->firstOrNew(['link_key' => $linkKey]);

        if ($link->exists) {
            if ($link->organization_id !== $venue->organization_id || $link->venue_id !== $venue->getKey()) {
                throw ValidationException::withMessages([
                    'source' => 'This booking link key is already assigned to another venue.',
                ]);
            }

            if ($link->trashed()) {
                $link->restore();
                $link->forceFill(['is_active' => true])->save();
            }

            return $link;
        }

        $link->fill([
            'organization_id' => $venue->organization_id,
            'venue_id' => $venue->getKey(),
            'external_booking_destination_id' => $destination->getKey(),
            'created_by_user_id' => $creator->getKey(),
            'destination' => VisibilityLinkDestination::ExternalBooking,
            'acquisition_source' => $source,
            'label' => filled($label) ? trim((string) $label) : $source->label(),
            'campaign' => $campaign,
            'token' => (string) Str::ulid(),
            'is_active' => true,
        ])->save();

        return $link;
    }
}
