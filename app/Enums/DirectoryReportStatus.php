<?php

namespace App\Enums;

enum DirectoryReportStatus: string
{
    case Pending = 'pending';
    case Resolved = 'resolved';
    case Dismissed = 'dismissed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending review',
            self::Resolved => 'Resolved',
            self::Dismissed => 'Dismissed',
        };
    }
}
