<?php

namespace App\Enums;

enum CommissionEntryStatus: string
{
    case Pending = 'pending';
    case Available = 'available';
    case Paid = 'paid';
    case Reversed = 'reversed';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
