<?php

namespace App\Enums;

enum DirectoryListingStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Closed = 'closed';
    case Claimed = 'claimed';
    case Removed = 'removed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft — not public',
            self::Published => 'Visible in venue guide',
            self::Closed => 'Venue marked closed',
            self::Claimed => 'Connected to owner',
            self::Removed => 'Hidden from venue guide',
        };
    }
}
