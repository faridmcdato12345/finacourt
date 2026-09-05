<?php

namespace App\Auth;

use App\Models\VenueClaimInvitation;
use Illuminate\Http\Request;

class OwnerClaimInvitationContext
{
    public function isPending(Request $request): bool
    {
        $intended = $request->session()->get('url.intended');

        if (! is_string($intended) || $intended === '') {
            return false;
        }

        $host = parse_url($intended, PHP_URL_HOST);

        if (is_string($host) && strcasecmp($host, $request->getHost()) !== 0) {
            return false;
        }

        $path = parse_url($intended, PHP_URL_PATH);

        if (! is_string($path)
            || preg_match('{^/owner/venue-invitations/([a-f0-9]{64})$}', $path, $matches) !== 1) {
            return false;
        }

        $invitation = VenueClaimInvitation::query()
            ->with('listing')
            ->where('token_hash', VenueClaimInvitation::hashToken($matches[1]))
            ->first();

        return $invitation?->isUsable() === true
            && $invitation->listing?->isClaimable() === true;
    }
}
