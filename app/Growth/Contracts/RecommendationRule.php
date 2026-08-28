<?php

namespace App\Growth\Contracts;

use App\Growth\GrowthRecommendation;
use App\Growth\GrowthRecommendationContext;
use Illuminate\Support\Collection;

interface RecommendationRule
{
    public function identifier(): string;

    /** @return Collection<int, GrowthRecommendation> */
    public function evaluate(GrowthRecommendationContext $context): Collection;
}
