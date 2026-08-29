<?php

namespace App\Enums;

enum OwnerPayoutStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Sent = 'sent';
    case Failed = 'failed';
    case Reversed = 'reversed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Waiting for approval',
            self::Approved => 'Ready to send',
            self::Sent => 'Sent',
            self::Failed => 'Could not be sent',
            self::Reversed => 'Returned',
            self::Cancelled => 'Cancelled',
        };
    }
}
