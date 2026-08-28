<?php

namespace App\Http\Requests;

use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;

class CancelBookingRequest extends FormRequest
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
            'cancellation_reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
