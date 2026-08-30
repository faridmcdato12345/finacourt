<?php

namespace App\Enums;

enum PlayerPaymentOption: string
{
    case Online = 'online';
    case PayAtVenue = 'pay_at_venue';

    public function label(): string
    {
        return match ($this) {
            self::Online => 'Pay online',
            self::PayAtVenue => 'Pay at venue',
        };
    }
}
