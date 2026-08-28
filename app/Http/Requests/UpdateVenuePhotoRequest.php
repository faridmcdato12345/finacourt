<?php

namespace App\Http\Requests;

use App\Models\Venue;
use App\Models\VenuePhoto;
use Illuminate\Foundation\Http\FormRequest;

class UpdateVenuePhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $venue = $this->route('venue');
        $photo = $this->route('photo');

        return $venue instanceof Venue
            && $photo instanceof VenuePhoto
            && $photo->venue_id === $venue->getKey()
            && $this->user()?->can('update', $venue) === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'alt_text' => ['sometimes', 'nullable', 'string', 'max:180'],
            'is_primary' => ['sometimes', 'accepted'],
        ];
    }
}
