<?php

namespace App\Http\Requests;

use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;

class BookingAvailabilityRequest extends FormRequest
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
            'date' => ['required', 'date_format:Y-m-d'],
            'duration_minutes' => ['required', 'integer', 'between:1,1440'],
        ];
    }
}
