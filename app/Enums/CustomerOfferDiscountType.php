<?php

namespace App\Enums;

enum CustomerOfferDiscountType: string
{
    case Fixed = 'fixed';
    case Percentage = 'percentage';

    public function label(): string
    {
        return match ($this) {
            self::Fixed => 'Fixed amount off',
            self::Percentage => 'Percentage off',
        };
    }
}
