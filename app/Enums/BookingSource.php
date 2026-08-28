<?php

namespace App\Enums;

enum BookingSource: string
{
    case Manual = 'manual';
    case WalkIn = 'walk_in';
    case Phone = 'phone';
    case Messenger = 'messenger';
    case Marketplace = 'marketplace';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Manual',
            self::WalkIn => 'Walk-in',
            self::Phone => 'Phone',
            self::Messenger => 'Messenger',
            self::Marketplace => 'Marketplace',
        };
    }
}
