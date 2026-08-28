<?php

namespace App\Enums;

enum PromotionType: string
{
    case Venue = 'venue';
    case Resource = 'resource';
    case TimeWindow = 'time_window';
    case SpecificSlots = 'specific_slots';
    case Deal = 'deal';

    public function label(): string
    {
        return match ($this) {
            self::Venue => 'Venue promotion',
            self::Resource => 'Court or resource promotion',
            self::TimeWindow => 'Time-window promotion',
            self::SpecificSlots => 'Specific available slots',
            self::Deal => 'Discount deal',
        };
    }
}
