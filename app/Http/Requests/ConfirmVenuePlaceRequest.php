<?php

namespace App\Http\Requests;

use App\Models\Venue;
use Illuminate\Foundation\Http\FormRequest;

class ConfirmVenuePlaceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $venue = $this->route('venue');

        return $venue instanceof Venue && $this->user()?->can('update', $venue) === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'place_reference' => ['required', 'string', 'max:512'],
        ];
    }
}
