<?php

namespace App\Enums;

enum ReactivationCampaignStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Sent = 'sent';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Active => 'Automation active',
            self::Sent => 'Sent',
            self::Cancelled => 'Cancelled',
        };
    }
}
