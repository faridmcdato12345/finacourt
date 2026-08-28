<?php

namespace App\Visibility;

use InvalidArgumentException;

readonly class PlaceCandidate
{
    public function __construct(
        public string $placeId,
        public string $formattedAddress,
        public float $latitude,
        public float $longitude,
        public string $source = 'google_places',
    ) {
        if ($placeId === '' || mb_strlen($placeId) > 2048) {
            throw new InvalidArgumentException('The resolved place identifier is invalid.');
        }

        if ($formattedAddress === '' || mb_strlen($formattedAddress) > 500) {
            throw new InvalidArgumentException('The resolved address is invalid.');
        }

        if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
            throw new InvalidArgumentException('The resolved coordinates are invalid.');
        }
    }
}
