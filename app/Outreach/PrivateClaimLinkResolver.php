<?php

namespace App\Outreach;

use App\Models\VenueClaimInvitation;
use Illuminate\Support\Carbon;

class PrivateClaimLinkResolver
{
    /**
     * @return array{
     *   invitation_id: int|null,
     *   listing_id: int|null,
     *   venue_id: int|null,
     *   claimed_at: Carbon|null
     * }
     */
    public function resolve(string $privateLink): array
    {
        $path = parse_url($privateLink, PHP_URL_PATH);

        if (! is_string($path)
            || preg_match('{/owner/venue-invitations/([a-f0-9]{64})/?$}', $path, $matches) !== 1) {
            return $this->emptyResult();
        }

        $invitation = VenueClaimInvitation::query()
            ->with('listing:id,claimed_venue_id,claimed_at')
            ->where('token_hash', VenueClaimInvitation::hashToken($matches[1]))
            ->first();

        if ($invitation === null) {
            return $this->emptyResult();
        }

        return [
            'invitation_id' => $invitation->getKey(),
            'listing_id' => $invitation->venue_directory_listing_id,
            'venue_id' => $invitation->listing?->claimed_venue_id,
            'claimed_at' => $invitation->listing?->claimed_at,
        ];
    }

    /** @return array{invitation_id: null, listing_id: null, venue_id: null, claimed_at: null} */
    private function emptyResult(): array
    {
        return [
            'invitation_id' => null,
            'listing_id' => null,
            'venue_id' => null,
            'claimed_at' => null,
        ];
    }
}
