<?php

namespace App\Enums;

enum GrowthRecommendationType: string
{
    case EmptyInventory = 'empty_inventory';
    case DemandWithInventory = 'demand_with_inventory';
    case UnfulfilledDemand = 'unfulfilled_demand';
    case InactiveCustomers = 'inactive_customers';
    case RepeatSuccessfulCampaign = 'repeat_successful_campaign';
    case LowBookingConversion = 'low_booking_conversion';
    case ChannelConversionGap = 'channel_conversion_gap';

    public function label(): string
    {
        return match ($this) {
            self::EmptyInventory => 'Open court times',
            self::DemandWithInventory => 'Players are searching',
            self::UnfulfilledDemand => 'Searches without a good match',
            self::InactiveCustomers => 'Past players to invite back',
            self::RepeatSuccessfulCampaign => 'Deal worth repeating',
            self::LowBookingConversion => 'Lots of visits, few bookings',
            self::ChannelConversionGap => 'Where players come from',
        };
    }
}
