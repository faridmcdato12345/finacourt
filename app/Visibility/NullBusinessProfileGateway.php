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
            'label' => 'Google profile connection unavailable',
            'detail' => 'Your marketplace listing and booking links still work without Google. Connection will appear only after approved API access is configured.',
        ];
    }
}
