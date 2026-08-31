<?php

namespace App\Google\BusinessProfile;

use App\Models\Venue;
use App\Visibility\VenuePublicUrl;

class GoogleBusinessProfileReadiness
{
    public function __construct(private readonly VenuePublicUrl $urls) {}

    /** @return array{score: int, checks: array<int, array<string, mixed>>, public_url: string, booking_url: string, public_page_ready: bool} */
    public function forVenue(Venue $venue): array
    {
        $venue->loadMissing([
            'sports:id,name,is_active',
            'resources:id,venue_id,sport_id,is_active',
            'resources.sport:id,name,is_active',
            'operatingHours:id,venue_id,day_of_week,is_closed,opens_at,closes_at',
        ]);
        $public = $this->urls->isPublic($venue);
        $hasAddress = filled($venue->address) && filled($venue->city) && filled($venue->province);
        $hasHours = $venue->operatingHours->count() === 7
            && $venue->operatingHours->contains(fn ($hours) => ! $hours->is_closed && filled($hours->opens_at) && filled($hours->closes_at));
        $hasCategory = $venue->sports->contains('is_active', true)
            && $venue->resources->contains(fn ($resource) => $resource->is_active && $resource->sport?->is_active);
        $checks = collect([
            $this->check('name', 'Venue name', 10, filled($venue->name), 'Add the name players already know.'),
            $this->check('address', 'Address and map pin', 20, $hasAddress && $venue->latitude !== null && $venue->longitude !== null, 'Add the full address and place the map pin at the entrance.'),
            $this->check('phone', 'Public phone number', 15, filled($venue->phone), 'Add a phone number players can use to reach the venue.'),
            $this->check('hours', 'Opening hours', 15, $hasHours, 'Set all seven days and keep at least one day open.'),
            $this->check('sport', 'Sport and bookable court', 15, $hasCategory, 'Choose the sports offered and keep at least one court active.'),
            $this->check('public_page', 'FinACourt booking page', 25, $public, 'Show the venue to players and keep at least one court bookable.'),
        ]);

        return [
            'score' => (int) $checks->where('complete', true)->sum('weight'),
            'checks' => $checks->values()->all(),
            'public_url' => $this->urls->canonical($venue),
            'booking_url' => $this->urls->googleBooking($venue),
            'public_page_ready' => $public,
        ];
    }

    /** @return array{code: string, label: string, weight: int, complete: bool, guidance: string} */
    private function check(string $code, string $label, int $weight, bool $complete, string $guidance): array
    {
        return compact('code', 'label', 'weight', 'complete', 'guidance');
    }
}
