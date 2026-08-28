<?php

namespace App\Visibility\Contracts;

use App\Visibility\PlaceCandidate;

interface PlacesProvider
{
    public function available(): bool;

    /**
     * Resolve an opaque provider reference selected and explicitly confirmed by
     * the owner. The browser is never trusted to supply place facts directly.
     */
    public function resolve(string $reference): ?PlaceCandidate;
}
