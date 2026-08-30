<?php

namespace App\Http\Requests;

use App\Enums\PlayerPaymentOption;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePlayerHoldRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'resource_id' => ['required', 'integer'],
            'booking_date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['required', 'date_format:H:i'],
            'duration_minutes' => [
                'required',
                'integer',
                'between:15,'.config('booking.maximum_player_booking_minutes'),
            ],
            'payment_option' => ['nullable', Rule::enum(PlayerPaymentOption::class)],
            'campaign' => ['nullable', 'string', 'max:40'],
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:40'],
            'terms' => ['accepted'],
        ];
    }
}
