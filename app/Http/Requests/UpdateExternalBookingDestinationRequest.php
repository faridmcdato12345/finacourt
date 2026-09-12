<?php

namespace App\Http\Requests;

use App\BookingLinks\ExternalBookingUrl;
use App\Models\Venue;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use InvalidArgumentException;

class UpdateExternalBookingDestinationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $venue = $this->route('venue');

        return $venue instanceof Venue && $this->user()?->can('update', $venue) === true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'destination_url' => trim((string) $this->input('destination_url')),
            'provider_name' => filled($this->input('provider_name'))
                ? trim((string) $this->input('provider_name'))
                : null,
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'destination_url' => ['required', 'string', 'max:2048'],
            'provider_name' => ['nullable', 'string', 'max:120'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->has('destination_url')) {
                return;
            }

            try {
                app(ExternalBookingUrl::class)->normalize((string) $this->input('destination_url'));
            } catch (InvalidArgumentException $exception) {
                $validator->errors()->add('destination_url', $exception->getMessage());
            }
        }];
    }
}
