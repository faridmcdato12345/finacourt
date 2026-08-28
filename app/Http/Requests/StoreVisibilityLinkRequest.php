<?php

namespace App\Http\Requests;

use App\Enums\VisibilityLinkDestination;
use App\Models\Venue;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVisibilityLinkRequest extends FormRequest
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
            'destination' => ['required', Rule::enum(VisibilityLinkDestination::class)],
            'promotion_id' => ['nullable', 'integer'],
        ];
    }
}
