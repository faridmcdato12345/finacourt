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

    public function description(): string
    {
        return match ($this) {
            self::FillEmptySlots => 'Use this when you have upcoming court times with no bookings. You can choose one or more open times below.',
            self::GetNewCustomers => 'Choose this when your main aim is to attract players who have not booked with you before. Other eligible players may still see the deal.',
            self::PromoteTodayOrTonight => 'Best for a court that is still open later today. Set today’s date and the available time below.',
            self::IncreaseOffPeakBookings => 'Use this for quieter weekday, morning, or afternoon hours. Choose the days and daily time range below.',
            self::PromoteSpecificSlots => 'Choose the exact court, date, and time you want to offer. You can add several court times to one deal.',
        };
    }
}
