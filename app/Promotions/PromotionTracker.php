<?php

namespace App\Promotions;

use App\Analytics\AnalyticsRecorder;
use App\Models\Promotion;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class PromotionTracker
{
    public function __construct(private readonly AnalyticsRecorder $analytics) {}

    /** @param Collection<int, Promotion> $promotions */
    public function recordImpressions(Request $request, Collection $promotions): void
    {
        foreach ($promotions as $promotion) {
            if ($this->analytics->recordPromotionImpression($request, $promotion)) {
                Promotion::query()->whereKey($promotion->getKey())->increment('impressions_count');
            }
        }
    }

    public function recordClick(Request $request, Promotion $promotion): void
    {
        if ($this->analytics->recordPromotionClick($request, $promotion)) {
            Promotion::query()->whereKey($promotion->getKey())->increment('clicks_count');
        }
    }
}
