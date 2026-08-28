<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVenueDirectoryReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'report_type' => ['required', Rule::in(['correction', 'closed', 'remove'])],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'details' => ['required', 'string', 'min:20', 'max:2000'],
        ];
    }
}
