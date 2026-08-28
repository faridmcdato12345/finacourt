<?php

namespace App\Visibility;

use App\Models\Venue;

class VisibilityScore
{
    /**
     * The weights intentionally total 100. This is a deterministic profile
     * readiness checklist, not a search-ranking prediction or guarantee.
     *
     * @return array{score: int, complete_weight: int, checks: array<int, array<string, mixed>>}
     */
    public function forVenue(Venue $venue): array
    {
        $venue->loadMissing([
            'sports:id,name,is_active',
            'resources:id,venue_id,sport_id,is_active,base_hourly_rate',
            'resources.sport:id,name,is_active',
            'operatingHours:id,venue_id,day_of_week,is_closed,opens_at,closes_at',
            'photos:id,venue_id,is_primary',
        ]);

        $activeResources = $venue->resources->filter(
            fn ($resource) => $resource->is_active && $resource->sport?->is_active,
        );
        $openHours = $venue->operatingHours->filter(
            fn ($hours) => ! $hours->is_closed && filled($hours->opens_at) && filled($hours->closes_at),
        );
        $hasCompleteLocation = filled($venue->address) && filled($venue->city) && filled($venue->province);
        $hasVerifiedPin = $venue->latitude !== null
            && $venue->longitude !== null
            && $venue->coordinates_verified_at !== null;
        $hasPhotoSet = $venue->photos->count() >= 3 && $venue->photos->contains('is_primary', true);
        $hasBookableInventory = $activeResources->contains(
            fn ($resource) => (float) $resource->base_hourly_rate >= 0,
        ) && $openHours->isNotEmpty();
        $checks = collect([
            $this->check('marketplace', 'Marketplace listing', 15, $venue->is_published && $activeResources->isNotEmpty(), 'Publish the venue and keep at least one court active.'),
            $this->check('description', 'Useful venue description', 10, mb_strlen(trim((string) $venue->description)) >= 80, 'Add at least 80 characters describing the facility and player experience.'),
            $this->check('address', 'Complete address', 10, $hasCompleteLocation, 'Add the street address, city or municipality, and province or region.'),
            $this->check('map_pin', 'Confirmed map pin', 10, $hasVerifiedPin, 'Confirm latitude and longitude so players can find the entrance.'),
            $this->check('photos', 'Photo coverage', 15, $hasPhotoSet, 'Add a cover photo and at least two more real venue photos.'),
            $this->check('hours', 'Opening hours', 10, $venue->operatingHours->count() === 7 && $openHours->isNotEmpty(), 'Configure all seven days and keep at least one day open.'),
            $this->check('sports', 'Sports and courts', 10, $venue->sports->contains('is_active', true) && $activeResources->isNotEmpty(), 'Select an active sport and configure an active court for it.'),
            $this->check('contact', 'Player contact details', 10, filled($venue->phone) || filled($venue->email) || filled($venue->website), 'Add at least one public phone number, email address, or website.'),
            $this->check('booking', 'Online booking ready', 10, $venue->is_published && $hasBookableInventory, 'Publish bookable inventory with opening hours and a valid base rate.'),
        ]);
        $completeWeight = $checks->where('complete', true)->sum('weight');

        return [
            'score' => (int) $completeWeight,
            'complete_weight' => (int) $completeWeight,
            'checks' => $checks->values()->all(),
        ];
    }

    /** @return array{code: string, label: string, weight: int, complete: bool, guidance: string} */
    private function check(string $code, string $label, int $weight, bool $complete, string $guidance): array
    {
        return compact('code', 'label', 'weight', 'complete', 'guidance');
    }
}
