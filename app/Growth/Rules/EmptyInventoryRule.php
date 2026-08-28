<?php

namespace App\Growth\Rules;

use App\Enums\GrowthRecommendationPriority;
use App\Enums\GrowthRecommendationType;
use App\Growth\Contracts\RecommendationRule;
use App\Growth\GrowthRecommendation;
use App\Growth\GrowthRecommendationContext;
use App\Models\Venue;
use Illuminate\Support\Collection;

class EmptyInventoryRule implements RecommendationRule
{
    public function identifier(): string
    {
        return 'empty_inventory_v1';
    }

    public function evaluate(GrowthRecommendationContext $context): Collection
    {
        $minimum = max(1, (int) config('growth.empty_inventory.minimum_slots', 6));
        $scanLimit = (int) config('growth.empty_inventory.scan_limit', 250);
        $horizon = (int) config('growth.empty_inventory.horizon_days', 7);
        $venues = $context->marketplaceVenues()->keyBy('id');

        return $context->emptySlots()
            ->groupBy('venue_id')
            ->filter(fn (Collection $slots) => $slots->count() >= $minimum)
            ->map(function (Collection $slots, int|string $venueId) use ($context, $venues, $scanLimit, $horizon): ?GrowthRecommendation {
                /** @var Venue|null $venue */
                $venue = $venues->get((int) $venueId);

                if ($venue === null) {
                    return null;
                }

                $first = $slots->first();
                $count = $slots->count();
                $lastMinute = $slots->where('is_last_minute', true)->count();
                $bounded = $context->emptySlots()->count() >= $scanLimit;
                $countPhrase = $bounded ? "at least {$count}" : (string) $count;

                return new GrowthRecommendation(
                    key: GrowthRecommendation::key(
                        GrowthRecommendationType::EmptyInventory,
                        $context->organization->getKey(),
                        $venue->getKey(),
                        'upcoming-slots',
                    ),
                    rule: $this->identifier(),
                    type: GrowthRecommendationType::EmptyInventory,
                    priority: $lastMinute > 0
                        ? GrowthRecommendationPriority::High
                        : GrowthRecommendationPriority::Medium,
                    organizationId: $context->organization->getKey(),
                    venueId: $venue->getKey(),
                    venueName: $venue->name,
                    title: "{$countPhrase} upcoming slots are still empty",
                    explanation: "A bounded {$horizon}-day inventory scan found {$countPhrase} bookable slots at {$venue->name} with no active reservation or existing slot promotion. {$lastMinute} are within 24 hours.",
                    evidence: [
                        'empty_slot_count' => $count,
                        'last_minute_slot_count' => $lastMinute,
                        'horizon_days' => $horizon,
                        'scan_capped' => $bounded,
                    ],
                    actionLabel: 'Promote a suggested slot',
                    actionUrl: route('owner.promotions.create', [
                        'resource' => $first['resource_id'],
                        'date' => $first['slot_date'],
                        'start' => $first['starts_at_time'],
                        'end' => $first['ends_at_time'],
                    ]),
                    calculatedAt: $context->calculatedAt,
                    expiresAt: $context->expiresAt,
                );
            })
            ->filter()
            ->values();
    }
}
