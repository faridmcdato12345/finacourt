<?php

namespace App\Enums;

enum VenueClaimProofStatus: string
{
    case Pending = 'pending';
    case Verified = 'verified';
    case Locked = 'locked';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Waiting for check',
            self::Verified => 'Venue email confirmed',
            self::Locked => 'Too many code tries',
        };
    }
}
