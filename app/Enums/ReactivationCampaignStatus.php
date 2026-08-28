<?php

namespace App\Enums;

enum ReactivationCampaignStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Sent => 'Sent',
            self::Cancelled => 'Cancelled',
        };
    }
}
