<?php

namespace App\Enums;

enum CourtClosureBookingStatus: string
{
    case Cancelled = 'cancelled';
    case ManualRefundRequired = 'manual_refund_required';
    case AwaitingApproval = 'awaiting_platform_approval';
    case Processing = 'processing';
    case Refunded = 'refunded';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Cancelled => 'Cancelled',
            self::ManualRefundRequired => 'Venue refund required',
            self::AwaitingApproval => 'Awaiting platform approval',
            self::Processing => 'Refund processing',
            self::Refunded => 'Refunded',
            self::Failed => 'Refund needs attention',
        };
    }
}
