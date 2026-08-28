<?php

namespace App\Visibility;

use App\Models\Venue;
use App\Visibility\Contracts\PlacesProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConfirmVenuePlace
{
    public function __construct(private readonly PlacesProvider $places) {}

    public function handle(Venue $venue, string $reference): Venue
    {
        if (! $this->places->available()) {
            throw ValidationException::withMessages([
                'place_reference' => 'Google place search is not configured. You can continue using the normal address and map pin fields.',
            ]);
        }

        $candidate = $this->places->resolve($reference);

        if ($candidate === null) {
            throw ValidationException::withMessages([
                'place_reference' => 'That place could not be verified. Search again or keep the existing address and map pin.',
            ]);
        }

        return DB::transaction(function () use ($venue, $candidate): Venue {
            $locked = Venue::query()->lockForUpdate()->findOrFail($venue->getKey());
            $locked->forceFill([
                'address' => $candidate->formattedAddress,
                'latitude' => $candidate->latitude,
                'longitude' => $candidate->longitude,
                'coordinates_source' => $candidate->source,
                'coordinates_verified_at' => now(),
                'google_place_id' => $candidate->placeId,
                'google_place_id_source' => $candidate->source,
                'google_place_id_verified_at' => now(),
            ])->save();

            return $locked;
        });
    }
}
