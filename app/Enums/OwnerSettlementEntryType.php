<?php

namespace App\Enums;

enum OwnerSettlementEntryType: string
{
    case BookingPayment = 'booking_payment';
    case RefundAdjustment = 'refund_adjustment';
    case PayoutReversal = 'payout_reversal';
    case AdminAdjustment = 'admin_adjustment';

    public function label(): string
    {
        return match ($this) {
            self::BookingPayment => 'Online booking payment',
            self::RefundAdjustment => 'Refund adjustment',
            self::PayoutReversal => 'Returned payout',
            self::AdminAdjustment => 'Manual correction',
        };
    }
}
