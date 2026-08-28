<?php

namespace App\Marketplace;

use App\Models\Venue;

class VenueMap
{
    /** @return null|array{embed_url: string, public_url: string, attribution_url: string} */
    public function forVenue(Venue $venue): ?array
    {
        if (
            $venue->latitude === null
            || $venue->longitude === null
            || $venue->coordinates_verified_at === null
        ) {
            return null;
        }

        $latitude = (float) $venue->latitude;
        $longitude = (float) $venue->longitude;
        $latitudeDelta = 0.006;
        $longitudeDelta = 0.009;
        $bbox = implode(',', [
            $longitude - $longitudeDelta,
            $latitude - $latitudeDelta,
            $longitude + $longitudeDelta,
            $latitude + $latitudeDelta,
        ]);

        return [
            'embed_url' => rtrim((string) config('maps.embed_base_url'), '?').'?'.http_build_query([
                'bbox' => $bbox,
                'layer' => 'mapnik',
                'marker' => $latitude.','.$longitude,
            ], '', '&', PHP_QUERY_RFC3986),
            'public_url' => rtrim((string) config('maps.public_base_url'), '/').'/?'.http_build_query([
                'mlat' => $latitude,
                'mlon' => $longitude,
            ], '', '&', PHP_QUERY_RFC3986).'#map=17/'.$latitude.'/'.$longitude,
            'attribution_url' => 'https://www.openstreetmap.org/copyright',
        ];
    }
}
