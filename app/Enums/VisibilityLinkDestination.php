<?php

namespace App\Enums;

enum VisibilityLinkDestination: string
{
    case Venue = 'venue';
    case Booking = 'booking';
    case Promotion = 'promotion';
    case ExternalBooking = 'external_booking';

    public function label(): string
    {
        return match ($this) {
            self::Venue => 'Venue page',
            self::Booking => 'Booking page',
            self::Promotion => 'Deal page',
            self::ExternalBooking => 'External booking page',
        };
    }
}
