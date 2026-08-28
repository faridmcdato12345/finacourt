<?php

namespace App\Enums;

enum PromotionGoal: string
{
    case FillEmptySlots = 'fill_empty_slots';
    case GetNewCustomers = 'get_new_customers';
    case PromoteTodayOrTonight = 'promote_today_or_tonight';
    case IncreaseOffPeakBookings = 'increase_off_peak_bookings';
    case PromoteSpecificSlots = 'promote_specific_slots';

    public function label(): string
    {
        return match ($this) {
            self::FillEmptySlots => 'Fill open court times',
            self::GetNewCustomers => 'Get first-time players',
            self::PromoteTodayOrTonight => 'Fill today or tonight',
            self::IncreaseOffPeakBookings => 'Fill slower hours',
            self::PromoteSpecificSlots => 'Pick exact court times',
        };
    }
}
