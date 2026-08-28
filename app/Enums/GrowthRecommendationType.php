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
            self::EmptyInventory => 'Empty upcoming inventory',
            self::DemandWithInventory => 'Demand with available inventory',
            self::UnfulfilledDemand => 'Unfulfilled marketplace demand',
            self::InactiveCustomers => 'Inactive previous customers',
            self::RepeatSuccessfulCampaign => 'Successful campaign to repeat',
            self::LowBookingConversion => 'High traffic with low conversion',
            self::ChannelConversionGap => 'Acquisition channel opportunity',
        };
    }
}
