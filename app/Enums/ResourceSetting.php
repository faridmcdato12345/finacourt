<?php

namespace App\Enums;

enum ResourceSetting: string
{
    case Indoor = 'indoor';
    case Outdoor = 'outdoor';
    case Covered = 'covered';

    public function label(): string
    {
        return match ($this) {
            self::Indoor => 'Indoor',
            self::Outdoor => 'Outdoor',
            self::Covered => 'Covered outdoor',
        };
    }
}
