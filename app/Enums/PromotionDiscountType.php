<?php

namespace App\Enums;

enum PromotionDiscountType: string
{
    case Percentage = 'percentage';
    case FixedHourlyRate = 'fixed_hourly_rate';

    public function label(): string
    {
        return match ($this) {
            self::Percentage => 'Percentage off',
            self::FixedHourlyRate => 'Special hourly rate',
        };
    }
}
