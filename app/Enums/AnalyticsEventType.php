<?php

namespace App\Enums;

enum AnalyticsEventType: string
{
    case MarketplaceSearch = 'marketplace_search';
    case VenueImpression = 'venue_impression';
    case VenueProfileView = 'venue_profile_view';
    case PromotionImpression = 'promotion_impression';
    case PromotionClick = 'promotion_click';
    case AvailabilityView = 'availability_view';
    case BookingStart = 'booking_start';
    case CompletedBooking = 'completed_booking';
}
