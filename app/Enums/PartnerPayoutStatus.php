<?php

namespace App\Enums;

enum PartnerPayoutStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Paid = 'paid';
    case Cancelled = 'cancelled';
}
