<?php

namespace App\Growth\Rules;

use App\Enums\GrowthRecommendationPriority;
use App\Enums\GrowthRecommendationType;
use App\Growth\Contracts\RecommendationRule;
use App\Growth\GrowthRecommendation;
use App\Growth\GrowthRecommendationContext;
use Illuminate\Support\Collection;

class InactiveCustomersRule implements RecommendationRule
{
    public function identifier(): string
    {
        return 'inactive_customers_v1';
    }

    public function evaluate(GrowthRecommendationContext $context): Collection
    {
        $count = $context->inactiveSegments()['inactive_30'];
        $minimum = max(1, (int) config('growth.inactive_customers.minimum_customers', 3));
        $inactiveDays = (int) config('reactivation.inactive_days', 30);
        $venue = $context->firstVenue();

        if ($count < $minimum || $venue === null) {
            return collect();
        }

        return collect([new GrowthRecommendation(
            key: GrowthRecommendation::key(
                GrowthRecommendationType::InactiveCustomers,
                $context->organization->getKey(),
                null,
                "inactive-{$inactiveDays}",
            ),
            rule: $this->identifier(),
            type: GrowthRecommendationType::InactiveCustomers,
            priority: GrowthRecommendationPriority::Medium,
            organizationId: $context->organization->getKey(),
            venueId: null,
            venueName: null,
            title: "{$count} past players have not booked in {$inactiveDays} days",
            explanation: "These {$count} players have booked with you before, but not in the last {$inactiveDays} days. FinACourt will only message players who agreed to receive messages and have not been contacted too recently.",
            evidence: [
                'inactive_customer_count' => $count,
                'inactive_days' => $inactiveDays,
                'minimum_group_size' => $minimum,
            ],
            actionLabel: 'Message past players',
            actionUrl: route('owner.reactivation.create', [
                'segment' => 'inactive_30',
                'venue' => $venue->getKey(),
            ]),
            calculatedAt: $context->calculatedAt,
            expiresAt: $context->expiresAt,
        )]);
    }
}
