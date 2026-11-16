<?php

namespace App\Enums;

enum CustomerOfferStatus: string
{
    case Available = 'available';
    case Reserved = 'reserved';
    case Redeemed = 'redeemed';
    case Expired = 'expired';
    case Revoked = 'revoked';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Available',
            self::Reserved => 'Reserved for checkout',
            self::Redeemed => 'Redeemed',
            self::Expired => 'Expired',
            self::Revoked => 'Revoked',
        };
    }
}
