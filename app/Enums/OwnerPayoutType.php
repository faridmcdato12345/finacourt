<?php

namespace App\Enums;

enum OwnerPayoutType: string
{
    case Scheduled = 'scheduled';
    case Early = 'early';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'Free scheduled payout',
            self::Early => 'Early payout',
        };
    }
}
