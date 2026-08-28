<?php

namespace App\Growth\Rules;

use App\Enums\GrowthRecommendationPriority;
use App\Enums\GrowthRecommendationType;
use App\Growth\Contracts\RecommendationRule;
use App\Growth\GrowthRecommendation;
use App\Growth\GrowthRecommendationContext;
use App\Models\Venue;
use Illuminate\Support\Collection;

class UnfulfilledDemandRule implements RecommendationRule
{
    public function identifier(): string
    {
        return 'unfulfilled_demand_v1';
    }

    public function evaluate(GrowthRecommendationContext $context): Collection
    {
        $minimum = max(1, (int) config('growth.demand.minimum_unfulfilled_searches', 3));

        return $context->marketplaceVenues()->map(function (Venue $venue) use ($context, $minimum): ?GrowthRecommendation {
            $sports = $venue->sports->keyBy('slug');
            $market = $context->demandMarkets()
                ->where('city_slug', $venue->city_slug)
                ->filter(fn (array $row) => $sports->has($row['sport_slug']))
                ->where('unfulfilled_searches', '>=', $minimum)
                ->sortByDesc('unfulfilled_searches')
                ->first();

            if ($market === null) {
                return null;
            }

            $sport = $sports->get($market['sport_slug']);
            $slot = $context->emptySlots()
                ->where('venue_id', $venue->getKey())
                ->where('sport_id', $sport->getKey())
                ->first();
            $action = $slot === null
                ? route('owner.venues.show', $venue)
                : route('owner.promotions.create', [
                    'resource' => $slot['resource_id'],
                    'date' => $slot['slot_date'],
                    'start' => $slot['starts_at_time'],
                    'end' => $slot['ends_at_time'],
                ]);

            return new GrowthRecommendation(
                key: GrowthRecommendation::key(
                    GrowthRecommendationType::UnfulfilledDemand,
                    $context->organization->getKey(),
                    $venue->getKey(),
                    (string) $market['sport_slug'],
                ),
                rule: $this->identifier(),
                type: GrowthRecommendationType::UnfulfilledDemand,
                priority: GrowthRecommendationPriority::High,
                organizationId: $context->organization->getKey(),
                venueId: $venue->getKey(),
                venueName: $venue->name,
                title: "{$market['unfulfilled_searches']} nearby {$sport->name} searches had no good match",
                explanation: "In {$venue->city}, {$market['unfulfilled_searches']} searches did not find a good match during the last {$context->lookbackDays} days: {$market['no_results']} found no matching venue and {$market['no_availability']} found venues but no bookable time. To protect players, counts appear only when enough different people searched.",
                evidence: [
                    'unfulfilled_searches' => $market['unfulfilled_searches'],
                    'no_results' => $market['no_results'],
                    'no_availability' => $market['no_availability'],
                    'unique_searchers' => $market['unique_searchers'],
                    'lookback_days' => $context->lookbackDays,
                    'city' => $venue->city,
                    'sport' => $sport->name,
                ],
                actionLabel: $slot === null ? 'Review your open times' : 'Create a deal for an open time',
                actionUrl: $action,
                calculatedAt: $context->calculatedAt,
                expiresAt: $context->expiresAt,
            );
        })->filter()->values();
    }
}
