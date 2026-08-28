<?php

namespace App\Enums;

enum BookingStatus: string
{
    case Hold = 'hold';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Hold => 'Temporary hold',
            self::Confirmed => 'Confirmed',
            self::Cancelled => 'Cancelled',
            self::Expired => 'Expired',
        };
    }
}
