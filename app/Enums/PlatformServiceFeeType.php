<?php

namespace App\Enums;

enum PlatformServiceFeeType: string
{
    case Percentage = 'percentage';
    case Fixed = 'fixed';

    public function label(): string
    {
        return match ($this) {
            self::Percentage => 'Percentage of court price',
            self::Fixed => 'Fixed amount per booking',
        };
    }
}
