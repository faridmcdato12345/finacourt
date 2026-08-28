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
            self::New => 'New customer',
            self::Returning => 'Returning customer',
            self::Inactive => 'Inactive customer',
        };
    }
}
