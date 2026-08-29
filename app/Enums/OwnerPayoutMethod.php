<?php

namespace App\Enums;

enum OwnerPayoutMethod: string
{
    case BankTransfer = 'bank_transfer';
    case Gcash = 'gcash';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::BankTransfer => 'Bank transfer',
            self::Gcash => 'GCash',
            self::Other => 'Other manual method',
        };
    }
}
