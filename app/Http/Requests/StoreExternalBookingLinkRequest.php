<?php

namespace App\Http\Requests;

use App\Models\Venue;
use App\Visibility\VisibilityLinkManager;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExternalBookingLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        $venue = $this->route('venue');

        return $venue instanceof Venue && $this->user()?->can('update', $venue) === true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'label' => filled($this->input('label')) ? trim((string) $this->input('label')) : null,
            'campaign' => filled($this->input('campaign')) ? strtolower(trim((string) $this->input('campaign'))) : null,
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'source' => [
                'required',
                Rule::in(collect(VisibilityLinkManager::externalSources())->map->value->all()),
            ],
            'label' => ['nullable', 'string', 'max:120'],
            'campaign' => ['nullable', 'string', 'max:120', 'regex:/^[a-z0-9][a-z0-9._-]*$/'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'campaign.regex' => 'Use letters, numbers, dots, dashes, or underscores for the campaign.',
        ];
    }
}
