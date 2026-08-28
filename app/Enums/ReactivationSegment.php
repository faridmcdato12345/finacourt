<?php

namespace App\Enums;

enum ReactivationSegment: string
{
    case Inactive30 = 'inactive_30';
    case Inactive60 = 'inactive_60';
    case PriorWeekday = 'prior_weekday';
    case Sport = 'sport';

    public function label(): string
    {
        return match ($this) {
            self::Inactive30 => 'No booking in 30 days',
            self::Inactive60 => 'No booking in 60 days',
            self::PriorWeekday => 'Prior weekday players',
            self::Sport => 'Previous players of a sport',
        };
    }
}
