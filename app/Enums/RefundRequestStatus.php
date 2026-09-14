<?php

namespace App\Enums;

enum RefundRequestStatus: string
{
    case Requested = 'requested';
    case Processing = 'processing';
    case Refunded = 'refunded';
    case Rejected = 'rejected';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Requested => 'Awaiting venue review',
            self::Processing => 'Refund processing',
            self::Refunded => 'Refunded',
            self::Rejected => 'Refund declined',
            self::Failed => 'Refund failed',
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Refunded, self::Rejected], true);
    }
}
