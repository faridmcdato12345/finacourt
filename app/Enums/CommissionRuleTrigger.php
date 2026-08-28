<?php

namespace App\Enums;

enum CommissionRuleTrigger: string
{
    case VenueActivation = 'venue_activation';

    public function label(): string
    {
        return 'Verified venue activation';
    }
}
