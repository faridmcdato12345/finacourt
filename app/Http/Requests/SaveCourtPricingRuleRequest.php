<?php

namespace App\Http\Requests;

use App\Models\CourtResource;
use App\Models\Venue;
use Illuminate\Foundation\Http\FormRequest;

class SaveCourtPricingRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $venue = $this->route('venue');
        $resource = $this->route('resource');

        return $venue instanceof Venue
            && $resource instanceof CourtResource
            && $resource->venue_id === $venue->getKey()
            && $this->user()?->can('update', $resource) === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'days_of_week' => ['required', 'array', 'min:1', 'max:7'],
            'days_of_week.*' => ['required', 'integer', 'distinct', 'between:0,6'],
            'starts_at_time' => ['required', 'date_format:H:i'],
            'ends_at_time' => ['required', 'date_format:H:i', 'after:starts_at_time'],
            'hourly_rate' => ['required', 'numeric', 'decimal:0,2', 'between:0,999999.99'],
        ];
    }

    public function messages(): array
    {
        return [
            'days_of_week.required' => 'Choose at least one day for this price.',
            'days_of_week.min' => 'Choose at least one day for this price.',
            'ends_at_time.after' => 'The ending time must be later than the starting time.',
        ];
    }
}
