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
            title: "{$count} previous customers have not booked in {$inactiveDays} days",
            explanation: "These {$count} account-linked customers completed a qualifying booking with your organization but have no qualifying booking in the last {$inactiveDays} days. Campaign delivery still requires consent and passes the existing frequency controls.",
            evidence: [
                'inactive_customer_count' => $count,
                'inactive_days' => $inactiveDays,
                'minimum_group_size' => $minimum,
            ],
            actionLabel: 'Create a comeback campaign',
            actionUrl: route('owner.reactivation.create', [
                'segment' => 'inactive_30',
                'venue' => $venue->getKey(),
            ]),
            calculatedAt: $context->calculatedAt,
            expiresAt: $context->expiresAt,
        )]);
    }
}
