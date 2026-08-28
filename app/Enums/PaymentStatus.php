<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending payment',
            self::Paid => 'Paid',
            self::Failed => 'Payment failed',
            self::Cancelled => 'Payment cancelled',
            self::Refunded => 'Refund recorded',
        };
    }
}
