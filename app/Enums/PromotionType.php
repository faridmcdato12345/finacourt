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
            self::Venue => 'Whole venue deal',
            self::Resource => 'Court deal',
            self::TimeWindow => 'Date and time deal',
            self::SpecificSlots => 'Exact court times',
            self::Deal => 'Discount deal',
        };
    }
}
