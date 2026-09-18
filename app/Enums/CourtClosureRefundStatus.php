<?php

namespace App\Enums;

enum CourtClosureRefundStatus: string
{
    case NotRequired = 'not_required';
    case AwaitingApproval = 'awaiting_platform_approval';
    case Processing = 'processing';
    case Completed = 'completed';
    case Attention = 'attention';

    public function label(): string
    {
        return match ($this) {
            self::NotRequired => 'No online refunds required',
            self::AwaitingApproval => 'Awaiting platform approval',
            self::Processing => 'Refunds processing',
            self::Completed => 'Refunds completed',
            self::Attention => 'Refunds need attention',
        };
    }
}
