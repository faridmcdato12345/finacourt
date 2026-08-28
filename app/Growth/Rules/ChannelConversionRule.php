<?php

namespace App\Growth\Rules;

use App\Enums\GrowthRecommendationPriority;
use App\Enums\GrowthRecommendationType;
use App\Growth\Contracts\RecommendationRule;
use App\Growth\GrowthRecommendation;
use App\Growth\GrowthRecommendationContext;
use Illuminate\Support\Collection;

class ChannelConversionRule implements RecommendationRule
{
    public function identifier(): string
    {
        return 'channel_conversion_gap_v1';
    }

    public function evaluate(GrowthRecommendationContext $context): Collection
    {
        $minimumVisitors = max(1, (int) config('growth.channel_comparison.minimum_visitors_per_channel', 20));
        $minimumBookings = max(1, (int) config('growth.channel_comparison.minimum_total_bookings', 3));
        $minimumGap = max(0, (float) config('growth.channel_comparison.minimum_gap_percentage_points', 5));
        $channels = $context->channelPerformance()
            ->where('unique_visitors', '>=', $minimumVisitors)
            ->sortByDesc('conversion_rate')
            ->values();

        if ($channels->count() < 2 || $channels->sum('bookings') < $minimumBookings) {
            return collect();
        }

        $best = $channels->first();
        $weakest = $channels->last();
        $gap = round($best['conversion_rate'] - $weakest['conversion_rate'], 1);

        if ($gap < $minimumGap) {
            return collect();
        }

        return collect([new GrowthRecommendation(
            key: GrowthRecommendation::key(
                GrowthRecommendationType::ChannelConversionGap,
                $context->organization->getKey(),
                null,
                "{$best['source']}-{$weakest['source']}",
            ),
            rule: $this->identifier(),
            type: GrowthRecommendationType::ChannelConversionGap,
            priority: GrowthRecommendationPriority::Low,
            organizationId: $context->organization->getKey(),
            venueId: null,
            venueName: null,
            title: "{$best['label']} is bringing better bookings than {$weakest['label']}",
            explanation: "{$best['label']} brought {$best['bookings']} confirmed bookings from {$best['unique_visitors']} different venue-page visitors ({$best['conversion_rate']}%). {$weakest['label']} brought {$weakest['bookings']} bookings from {$weakest['unique_visitors']} visitors ({$weakest['conversion_rate']}%). Use this as a helpful sign, not perfect proof.",
            evidence: [
                'stronger_source' => $best['source'],
                'stronger_unique_visitors' => $best['unique_visitors'],
                'stronger_qualified_bookings' => $best['bookings'],
                'stronger_conversion_rate_percent' => $best['conversion_rate'],
                'comparison_source' => $weakest['source'],
                'comparison_unique_visitors' => $weakest['unique_visitors'],
                'comparison_qualified_bookings' => $weakest['bookings'],
                'comparison_conversion_rate_percent' => $weakest['conversion_rate'],
                'gap_percentage_points' => $gap,
                'lookback_days' => $context->lookbackDays,
            ],
            actionLabel: 'Review where bookings come from',
            actionUrl: route('owner.analytics'),
            calculatedAt: $context->calculatedAt,
            expiresAt: $context->expiresAt,
        )]);
    }
}
