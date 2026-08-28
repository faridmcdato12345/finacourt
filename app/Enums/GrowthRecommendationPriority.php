<?php

namespace App\Enums;

enum GrowthRecommendationPriority: string
{
    case High = 'high';
    case Medium = 'medium';
    case Low = 'low';

    public function weight(): int
    {
        return match ($this) {
            self::High => 30,
            self::Medium => 20,
            self::Low => 10,
        };
    }
}
