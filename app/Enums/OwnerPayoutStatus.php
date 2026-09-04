<?php

namespace App\Enums;

enum OwnerPayoutStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Processing = 'processing';
    case Paid = 'paid';
    // Kept so historical production rows created by the original workflow
    // remain readable. New completed payouts use Paid.
    case Sent = 'sent';
    case Failed = 'failed';
    case Reversed = 'reversed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Queued',
            self::Approved => 'Approved',
            self::Processing => 'Processing',
            self::Paid, self::Sent => 'Paid',
            self::Failed => 'Could not be sent',
            self::Reversed => 'Returned',
            self::Cancelled => 'Cancelled',
        };
    }
}
