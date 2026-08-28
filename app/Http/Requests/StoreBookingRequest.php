<?php

namespace App\Http\Requests;

use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBookingRequest extends FormRequest
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
            'booking_date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'status' => ['required', Rule::in([BookingStatus::Hold->value, BookingStatus::Confirmed->value])],
            'source' => ['required', Rule::in([
                BookingSource::Manual->value,
                BookingSource::WalkIn->value,
                BookingSource::Phone->value,
                BookingSource::Messenger->value,
            ])],
            'hold_minutes' => [
                'nullable',
                'required_if:status,'.BookingStatus::Hold->value,
                'integer',
                'between:1,'.config('booking.maximum_hold_minutes'),
            ],
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:40'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
