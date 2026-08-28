<?php

namespace App\Enums;

enum PromotionStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Active = 'active';
    case Paused = 'paused';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Scheduled => 'Scheduled',
            self::Active => 'Active',
            self::Paused => 'Paused',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
        };
    }

    public function acceptsBookings(): bool
    {
        return in_array($this, [self::Scheduled, self::Active], true);
    }

    public function canTransitionTo(self $target): bool
    {
        if ($this === $target) {
            return true;
        }

        return in_array($target, match ($this) {
            self::Draft => [self::Scheduled, self::Active, self::Cancelled],
            self::Scheduled => [self::Active, self::Paused, self::Cancelled],
            self::Active => [self::Paused, self::Completed, self::Cancelled],
            self::Paused => [self::Scheduled, self::Active, self::Cancelled],
            self::Completed, self::Cancelled => [],
        }, true);
    }
}
