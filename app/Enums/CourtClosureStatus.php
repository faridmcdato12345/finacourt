<?php

namespace App\Enums;

enum CourtClosureStatus: string
{
    case Active = 'active';
    case Reopened = 'reopened';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Court closed',
            self::Reopened => 'Reopened',
        };
    }
}
