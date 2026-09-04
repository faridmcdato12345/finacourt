<?php

namespace App\Promotions;

use App\Bookings\BookingPrice;
use App\Bookings\BookingWindow;
use App\Models\CourtResource;
use App\Models\Promotion;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class PromotionApplicability
{
    public function __construct(private readonly BookingPrice $prices) {}

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

    /** @param Collection<int, Promotion> $candidates */
    public function bestDiscount(
        Collection $candidates,
        CourtResource $resource,
        BookingWindow $window,
    ): ?Promotion {
        return $candidates
            ->filter(fn (Promotion $candidate) => $candidate->appliesTo(
                $resource,
                $window->localStart,
                $window->localEnd,
            ))
            ->map(fn (Promotion $candidate) => [
                'promotion' => $candidate,
                'price' => $this->prices->quote($resource, $window->durationMinutes, $candidate),
            ])
            ->filter(fn (array $candidate) => (float) $candidate['price']['discount_amount'] > 0)
            ->sort(function (array $left, array $right): int {
                $priceComparison = (float) $left['price']['total_amount'] <=> (float) $right['price']['total_amount'];

                return $priceComparison !== 0
                    ? $priceComparison
                    : $left['promotion']->getKey() <=> $right['promotion']->getKey();
            })
            ->first()['promotion'] ?? null;
    }
}
