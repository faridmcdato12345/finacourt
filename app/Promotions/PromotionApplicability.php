<?php

namespace App\Promotions;

use App\Bookings\BookingWindow;
use App\Models\CourtResource;
use App\Models\Promotion;
use Illuminate\Validation\ValidationException;

class PromotionApplicability
{
    public function resolve(
        CourtResource $resource,
        BookingWindow $window,
        ?string $campaignToken,
        bool $lockForUpdate = false,
    ): ?Promotion {
        if (! filled($campaignToken)) {
            return null;
        }

        $resource->loadMissing('venue.organization');
        $query = Promotion::query()
            ->with('slots')
            ->where('campaign_token', $campaignToken)
            ->where('organization_id', $resource->venue->organization_id)
            ->where('venue_id', $resource->venue_id)
            ->where(function ($query) use ($resource): void {
                $query->whereNull('resource_id')->orWhere('resource_id', $resource->getKey());
            });

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        $promotion = $query->first();

        if (! $promotion || ! $promotion->appliesTo($resource, $window->localStart, $window->localEnd)) {
            throw ValidationException::withMessages([
                'campaign' => 'This deal is off, expired, or does not apply to the selected court and time.',
            ]);
        }

        return $promotion;
    }
}
