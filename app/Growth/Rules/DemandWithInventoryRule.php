<?php

namespace App\Growth\Rules;

use App\Enums\GrowthRecommendationPriority;
use App\Enums\GrowthRecommendationType;
use App\Growth\Contracts\RecommendationRule;
use App\Growth\GrowthRecommendation;
use App\Growth\GrowthRecommendationContext;
use App\Models\Venue;
use Illuminate\Support\Collection;

class DemandWithInventoryRule implements RecommendationRule
{
    public function identifier(): string
    {
        return 'demand_with_inventory_v1';
    }

    public function evaluate(GrowthRecommendationContext $context): Collection
    {
        $minimumSearches = max(1, (int) config('growth.demand.minimum_searches', 6));
        $minimumSlots = max(1, (int) config('growth.demand.minimum_available_slots', 3));

        return $context->marketplaceVenues()->map(function (Venue $venue) use ($context, $minimumSearches, $minimumSlots): ?GrowthRecommendation {
            $sports = $venue->sports->keyBy('slug');
            $market = $context->demandMarkets()
                ->where('city_slug', $venue->city_slug)
                ->filter(fn (array $row) => $sports->has($row['sport_slug']))
                ->where('searches', '>=', $minimumSearches)
                ->sortByDesc('searches')
                ->first();

            if ($market === null) {
                return null;
            }

            $sport = $sports->get($market['sport_slug']);
            $slots = $context->emptySlots()
                ->where('venue_id', $venue->getKey())
                ->where('sport_id', $sport->getKey())
                ->values();

            if ($slots->count() < $minimumSlots) {
                return null;
            }

            $first = $slots->first();

            return new GrowthRecommendation(
                key: GrowthRecommendation::key(
                    GrowthRecommendationType::DemandWithInventory,
                    $context->organization->getKey(),
                    $venue->getKey(),
                    (string) $market['sport_slug'],
                ),
                rule: $this->identifier(),
                type: GrowthRecommendationType::DemandWithInventory,
                priority: GrowthRecommendationPriority::High,
                organizationId: $context->organization->getKey(),
                venueId: $venue->getKey(),
                venueName: $venue->name,
                title: "Players are searching for {$sport->name} while you have open times",
                explanation: "{$market['searches']} searches from {$market['unique_searchers']} different searchers looked for {$sport->name} in {$venue->city} during the last {$context->lookbackDays} days. You still have {$slots->count()} upcoming {$sport->name} times available.",
                evidence: [
                    'searches' => $market['searches'],
                    'unique_searchers' => $market['unique_searchers'],
                    'available_slot_count' => $slots->count(),
                    'lookback_days' => $context->lookbackDays,
                    'city' => $venue->city,
                    'sport' => $sport->name,
                ],
                actionLabel: 'Create a promotion',
                actionUrl: route('owner.promotions.create', [
                    'resource' => $first['resource_id'],
                    'date' => $first['slot_date'],
                    'start' => $first['starts_at_time'],
                    'end' => $first['ends_at_time'],
                ]),
                calculatedAt: $context->calculatedAt,
                expiresAt: $context->expiresAt,
            );
        })->filter()->values();
    }
}
