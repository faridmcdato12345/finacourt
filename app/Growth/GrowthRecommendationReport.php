<?php

namespace App\Growth;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

readonly class GrowthRecommendationReport
{
    /**
     * @param  Collection<int, GrowthRecommendation>  $active
     * @param  Collection<int, GrowthRecommendation>  $suppressed
     */
    public function __construct(
        public Collection $active,
        public Collection $suppressed,
        public CarbonImmutable $calculatedAt,
        public int $lookbackDays,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'calculated_at' => $this->calculatedAt->toIso8601String(),
            'lookback_days' => $this->lookbackDays,
            'active' => $this->active->map->toArray()->values()->all(),
            'suppressed' => $this->suppressed->map->toArray()->values()->all(),
            'has_sufficient_data' => $this->active->isNotEmpty() || $this->suppressed->isNotEmpty(),
        ];
    }
}
