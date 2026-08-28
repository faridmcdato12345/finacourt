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
            self::FillEmptySlots => 'Fill empty courts',
            self::GetNewCustomers => 'Get new customers',
            self::PromoteTodayOrTonight => 'Promote today or tonight',
            self::IncreaseOffPeakBookings => 'Increase off-peak bookings',
            self::PromoteSpecificSlots => 'Promote specific slots',
        };
    }
}
