<?php

namespace App\Visibility;

use App\Models\Venue;
use App\Visibility\Contracts\BusinessProfileGateway;

class NullBusinessProfileGateway implements BusinessProfileGateway
{
    public function available(): bool
    {
        return false;
    }

    public function status(Venue $venue): array
    {
        return [
            'status' => 'unavailable',
            'label' => 'Google is not connected',
            'detail' => 'Your FinACourt page and booking links still work without Google. A Google connection can be added later when it is set up.',
        ];
    }
}
