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
            self::Pending => 'Waiting for proof',
            self::Verified => 'Ownership proof confirmed',
            self::Locked => 'Code attempts locked',
        };
    }
}
