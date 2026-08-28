<?php

namespace App\Http\Requests;

use App\Enums\PaymentStatus;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateManualPaymentRequest extends FormRequest
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
            'status' => ['required', Rule::in([
                PaymentStatus::Paid->value,
                PaymentStatus::Failed->value,
                PaymentStatus::Cancelled->value,
                PaymentStatus::Refunded->value,
            ])],
            'note' => ['nullable', 'required_if:status,'.PaymentStatus::Refunded->value, 'string', 'max:500'],
        ];
    }
}
