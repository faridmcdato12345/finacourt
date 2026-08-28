<?php

namespace App\Visibility;

use App\Visibility\Contracts\PlacesProvider;

class NullPlacesProvider implements PlacesProvider
{
    public function available(): bool
    {
        return false;
    }

    public function resolve(string $reference): ?PlaceCandidate
    {
        return null;
    }
}
