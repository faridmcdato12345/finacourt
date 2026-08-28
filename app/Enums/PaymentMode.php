<?php

namespace App\Enums;

enum PaymentMode: string
{
    case PayAtVenue = 'pay_at_venue';
    case HostedCheckout = 'hosted_checkout';

    public function label(): string
    {
        return match ($this) {
            self::PayAtVenue => 'Pay at venue',
            self::HostedCheckout => 'Hosted checkout',
        };
    }
}
