<?php

namespace App\Enums;

enum SalesLeadStatus: string
{
    case New = 'new';
    case Contacted = 'contacted';
    case DemoScheduled = 'demo_scheduled';
    case Interested = 'interested';
    case Onboarding = 'onboarding';
    case Activated = 'activated';
    case Won = 'won';
    case Lost = 'lost';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::New => 'New',
            self::Contacted => 'Contacted',
            self::DemoScheduled => 'Demo scheduled',
            self::Interested => 'Interested',
            self::Onboarding => 'Onboarding',
            self::Activated => 'Activated',
            self::Won => 'Won',
            self::Lost => 'Lost',
            self::Expired => 'Expired',
        };
    }

    /** @return list<self> */
    public function next(): array
    {
        return match ($this) {
            self::New => [self::Contacted, self::Lost, self::Expired],
            self::Contacted => [self::DemoScheduled, self::Interested, self::Lost, self::Expired],
            self::DemoScheduled => [self::Contacted, self::Interested, self::Lost, self::Expired],
            self::Interested => [self::Onboarding, self::Lost, self::Expired],
            self::Onboarding => [self::Activated, self::Lost, self::Expired],
            self::Activated => [self::Won, self::Lost],
            self::Won, self::Lost, self::Expired => [],
        };
    }
}
