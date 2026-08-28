<?php

namespace App\Growth\Rules;

use App\Enums\GrowthRecommendationPriority;
use App\Enums\GrowthRecommendationType;
use App\Growth\Contracts\RecommendationRule;
use App\Growth\GrowthRecommendation;
use App\Growth\GrowthRecommendationContext;
use Illuminate\Support\Collection;

class LowBookingConversionRule implements RecommendationRule
{
    public function identifier(): string
    {
        return 'low_booking_conversion_v1';
    }

    public function evaluate(GrowthRecommendationContext $context): Collection
    {
        $minimumViews = max(1, (int) config('growth.low_conversion.minimum_profile_views', 20));
        $minimumVisitors = max(1, (int) config('growth.low_conversion.minimum_unique_visitors', 10));
        $maximumRate = max(0, (float) config('growth.low_conversion.maximum_booking_rate_percent', 5));

        return $context->venueConversions()
            ->filter(fn (array $venue) => $venue['profile_views'] >= $minimumViews
                && $venue['unique_visitors'] >= $minimumVisitors
                && $venue['conversion_rate'] <= $maximumRate)
            ->map(fn (array $venue) => new GrowthRecommendation(
                key: GrowthRecommendation::key(
                    GrowthRecommendationType::LowBookingConversion,
                    $context->organization->getKey(),
                    $venue['venue_id'],
                    'profile-to-booking',
                ),
                rule: $this->identifier(),
                type: GrowthRecommendationType::LowBookingConversion,
                priority: GrowthRecommendationPriority::High,
                organizationId: $context->organization->getKey(),
                venueId: $venue['venue_id'],
                venueName: $venue['venue_name'],
                title: "{$venue['venue_name']} gets views but few confirmed bookings",
                explanation: "The venue received {$venue['profile_views']} profile views from {$venue['unique_visitors']} estimated visitors and {$venue['bookings']} qualified bookings in the last {$context->lookbackDays} days, a {$venue['conversion_rate']}% aggregate view-to-booking rate.",
                evidence: [
                    'profile_views' => $venue['profile_views'],
                    'unique_visitors' => $venue['unique_visitors'],
                    'qualified_bookings' => $venue['bookings'],
                    'conversion_rate_percent' => $venue['conversion_rate'],
                    'lookback_days' => $context->lookbackDays,
                ],
                actionLabel: 'Improve visibility profile',
                actionUrl: route('owner.visibility.index', ['venue' => $venue['venue_id']]),
                calculatedAt: $context->calculatedAt,
                expiresAt: $context->expiresAt,
            ))
            ->values();
    }
}
