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
            self::Pending => 'Waiting for independent check',
            self::Verified => 'Ownership check confirmed',
            self::Locked => 'Manual check required',
        };
    }
}
