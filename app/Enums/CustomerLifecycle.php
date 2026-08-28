<?php

namespace App\Enums;

enum CustomerLifecycle: string
{
    case New = 'new';
    case Returning = 'returning';
    case Inactive = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::New => 'First-time player',
            self::Returning => 'Returning player',
            self::Inactive => 'Past player',
        };
    }
}
