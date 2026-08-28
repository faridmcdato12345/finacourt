<?php

namespace App\Enums;

enum DirectoryClaimStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'We’re reviewing it',
            self::Approved => 'Added to your account',
            self::Rejected => 'Couldn’t confirm',
            self::Cancelled => 'Cancelled',
        };
    }
}
