<?php

namespace App\Http\Requests;

use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCourtClosureRequest extends FormRequest
{
    public function authorize(): bool
    {
        $context = app(TenantContext::class);

        return $context->hasOrganization()
            && $this->user()?->can('manageBookings', $context->organization()) === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'scope' => ['required', Rule::in(['court', 'selected_courts', 'venue'])],
            'resource_id' => ['exclude_unless:scope,court', 'required', 'integer'],
            'resource_ids' => ['exclude_unless:scope,selected_courts', 'required', 'array', 'min:2'],
            'resource_ids.*' => ['integer', 'distinct'],
            'venue_id' => ['exclude_unless:scope,venue', 'required', 'integer'],
            'starts_at' => ['required', 'date_format:Y-m-d\TH:i'],
            'until_reopened' => ['required', 'boolean'],
            'ends_at' => [Rule::requiredIf(fn () => ! $this->boolean('until_reopened')), 'nullable', 'date_format:Y-m-d\TH:i'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
            'confirmed' => ['nullable', 'boolean'],
            'confirmation_token' => [Rule::requiredIf(fn () => $this->boolean('confirmed')), 'nullable', 'string', 'max:100'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $resourceIds = collect($this->input('resource_ids', []))
            ->filter(fn ($value) => is_numeric($value))
            ->map(fn ($value) => (int) $value)
            ->unique()
            ->values()
            ->all();

        $this->merge([
            'until_reopened' => $this->boolean('until_reopened'),
            'confirmed' => $this->boolean('confirmed'),
            'resource_ids' => $resourceIds,
            'reason' => is_string($this->input('reason')) ? trim($this->input('reason')) : $this->input('reason'),
        ]);
    }
}
