<?php

namespace App\Visibility;

use App\Models\Venue;

class VenuePublicUrl
{
    public function canonical(Venue $venue): string
    {
        return route('marketplace.venues.show', ['venueSlug' => $venue->slug]);
    }

    public function googleBooking(Venue $venue): string
    {
        return route('marketplace.venues.show', [
            'venueSlug' => $venue->slug,
            'utm_source' => 'google',
            'utm_medium' => 'business-profile',
        ]).'#availability';
    }

    public function isPublic(Venue $venue): bool
    {
        return Venue::query()->marketplace()->whereKey($venue->getKey())->exists();
    }
}
