<?php

namespace App\Http\Requests;

use App\Enums\ResourceSetting;
use App\Enums\ResourceType;
use App\Models\Venue;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCourtResourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $venue = $this->route('venue');

        return $venue instanceof Venue && $this->user()?->can('manageInventory', $venue->organization) === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var Venue $venue */
        $venue = $this->route('venue');

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('resources', 'name')->where('venue_id', $venue->getKey())],
            'sport_id' => [
                'required',
                'integer',
                Rule::exists('sport_venue', 'sport_id')->where('venue_id', $venue->getKey()),
            ],
            'resource_type' => ['required', Rule::enum(ResourceType::class)],
            'setting' => ['required', Rule::enum(ResourceSetting::class)],
            'is_active' => ['required', 'boolean'],
            'base_hourly_rate' => ['required', 'numeric', 'decimal:0,2', 'between:0,999999.99'],
            'booking_increment_minutes' => ['required', 'integer', Rule::in([15, 30, 45, 60, 90, 120])],
        ];
    }
}
