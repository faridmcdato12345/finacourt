<?php

namespace App\Enums;

enum DemandSearchOutcome: string
{
    case ResultsAvailable = 'results_available';
    case VenuesFoundNoAvailability = 'venues_found_no_availability';
    case NoResults = 'no_results';

    public function label(): string
    {
        return match ($this) {
            self::ResultsAvailable => 'Results available',
            self::VenuesFoundNoAvailability => 'Venues found, no availability',
            self::NoResults => 'No matching venues',
        };
    }
}
