<?php

namespace App\Enums;

enum PromotionDiscountType: string
{
    case Percentage = 'percentage';
    case FixedHourlyRate = 'fixed_hourly_rate';
    case BonusHours = 'bonus_hours';

    public function label(): string
    {
        return match ($this) {
            self::Percentage => 'Percentage off',
            self::FixedHourlyRate => 'Special hourly rate',
            self::BonusHours => 'Bonus court time',
        };
    }
}
