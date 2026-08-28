<?php

namespace App\Enums;

enum GrowthRecommendationStateStatus: string
{
    case Dismissed = 'dismissed';
    case Snoozed = 'snoozed';
    case Resolved = 'resolved';

    public function label(): string
    {
        return match ($this) {
            self::Dismissed => 'Dismissed',
            self::Snoozed => 'Snoozed',
            self::Resolved => 'Resolved',
        };
    }
}
