<?php

namespace App\Visibility;

use App\Models\Venue;
use App\Models\VenueDirectoryListing;

class GoogleDirections
{
    public function forVenue(Venue $venue): ?string
    {
        $address = collect([$venue->name, $venue->address, $venue->city, $venue->province])
            ->filter(fn ($value) => is_string($value) && trim($value) !== '')
            ->implode(', ');

        return $this->build(
            $address,
            $venue->latitude,
            $venue->longitude,
            $venue->coordinates_verified_at !== null,
            $venue->google_place_id,
            filled($venue->google_place_id_source) && $venue->google_place_id_verified_at !== null,
        );
    }

    public function forDirectoryListing(VenueDirectoryListing $listing): ?string
    {
        $address = collect([
            $listing->name,
            $listing->address,
            $listing->city,
            $listing->province,
            $listing->country,
        ])->filter(fn ($value) => is_string($value) && trim($value) !== '')->implode(', ');

        return $this->build(
            $address,
            $listing->latitude,
            $listing->longitude,
            $listing->coordinates_verified_at !== null,
        );
    }

    private function build(
        string $address,
        mixed $latitude,
        mixed $longitude,
        bool $coordinatesVerified,
        ?string $placeId = null,
        bool $placeIdVerified = false,
    ): ?string {

        if ($address === '' && ($latitude === null || $longitude === null)) {
            return null;
        }

        $destination = $coordinatesVerified
            && $latitude !== null
            && $longitude !== null
                ? $latitude.','.$longitude
                : $address;
        $parameters = [
            'api' => 1,
            'destination' => $destination,
        ];

        // A Place ID is accepted only when it was resolved by the configured
        // provider and explicitly confirmed. Raw venue form input cannot set it.
        if (
            filled($placeId)
            && $placeIdVerified
        ) {
            // Google Maps URLs require a destination alongside a place ID.
            $parameters['destination'] = $address !== '' ? $address : $destination;
            $parameters['destination_place_id'] = $placeId;
        }

        $url = 'https://www.google.com/maps/dir/?'.http_build_query(
            $parameters,
            '',
            '&',
            PHP_QUERY_RFC3986,
        );

        // Maps URLs are limited to 2,048 characters. A Place ID has no fixed
        // maximum length, so fall back to the already verified coordinates or
        // bounded venue address rather than emitting a truncated identifier.
        if (strlen($url) > 2048 && array_key_exists('destination_place_id', $parameters)) {
            unset($parameters['destination_place_id']);
            $parameters['destination'] = $destination;
            $url = 'https://www.google.com/maps/dir/?'.http_build_query(
                $parameters,
                '',
                '&',
                PHP_QUERY_RFC3986,
            );
        }

        return $url;
    }
}
