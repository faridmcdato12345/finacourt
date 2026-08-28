<?php

namespace App\Growth\Rules;

use App\Enums\GrowthRecommendationPriority;
use App\Enums\GrowthRecommendationType;
use App\Growth\Contracts\RecommendationRule;
use App\Growth\GrowthRecommendation;
use App\Growth\GrowthRecommendationContext;
use Illuminate\Support\Collection;

class SuccessfulCampaignRule implements RecommendationRule
{
    public function identifier(): string
    {
        return 'successful_campaign_v1';
    }

    public function evaluate(GrowthRecommendationContext $context): Collection
    {
        $minimum = max(1, (int) config('growth.successful_campaign.minimum_bookings', 3));

        return $context->promotionPerformance()
            ->filter(fn (array $campaign) => $campaign['bookings'] >= $minimum
                && $campaign['revenue'] > 0
                && $campaign['single_currency'])
            ->take(3)
            ->map(fn (array $campaign) => new GrowthRecommendation(
                key: GrowthRecommendation::key(
                    GrowthRecommendationType::RepeatSuccessfulCampaign,
                    $context->organization->getKey(),
                    $campaign['venue_id'],
                    "promotion-{$campaign['promotion_id']}",
                ),
                rule: $this->identifier(),
                type: GrowthRecommendationType::RepeatSuccessfulCampaign,
                priority: GrowthRecommendationPriority::Medium,
                organizationId: $context->organization->getKey(),
                venueId: $campaign['venue_id'],
                venueName: $campaign['venue_name'],
                title: "“{$campaign['title']}” produced {$campaign['bookings']} bookings",
                explanation: "The campaign generated {$campaign['bookings']} qualified marketplace bookings worth ".number_format($campaign['revenue'], 2)." {$campaign['currency']} during the last {$context->lookbackDays} days. Review the actual campaign before deciding whether to repeat it.",
                evidence: [
                    'promotion_id' => $campaign['promotion_id'],
                    'qualified_bookings' => $campaign['bookings'],
                    'qualified_booking_value' => round($campaign['revenue'], 2),
                    'currency' => $campaign['currency'],
                    'lookback_days' => $context->lookbackDays,
                    'campaign_status' => $campaign['status'],
                ],
                actionLabel: 'Review campaign',
                actionUrl: route('owner.promotions.show', $campaign['promotion_id']),
                calculatedAt: $context->calculatedAt,
                expiresAt: $context->expiresAt,
            ))
            ->values();
    }
}
