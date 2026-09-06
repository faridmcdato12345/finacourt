<?php

namespace App\Enums;

enum VenueApplicationStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Waiting for ownership review',
            self::Approved => 'Ownership approved',
            self::Rejected => 'Changes required',
        };
    }
}
