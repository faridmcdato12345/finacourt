<?php

namespace App\Http\Requests;

use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCourtAvailabilityBlockRequest extends FormRequest
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
            'resource_id' => ['required', 'integer'],
            'block_date' => ['required', 'date_format:Y-m-d'],
            'is_all_day' => ['required', 'boolean'],
            'start_time' => [Rule::requiredIf(fn () => ! $this->boolean('is_all_day')), 'nullable', 'date_format:H:i'],
            'end_time' => [Rule::requiredIf(fn () => ! $this->boolean('is_all_day')), 'nullable', 'date_format:H:i'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
            'repeat' => ['required', Rule::in(['none', 'daily', 'weekly'])],
            'repeat_until' => [
                Rule::requiredIf(fn () => $this->input('repeat') !== 'none'),
                'nullable',
                'date_format:Y-m-d',
                'after_or_equal:block_date',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_all_day' => $this->boolean('is_all_day'),
            'reason' => is_string($this->input('reason')) ? trim($this->input('reason')) : $this->input('reason'),
        ]);
    }
}
