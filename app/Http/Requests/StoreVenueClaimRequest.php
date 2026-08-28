<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVenueClaimRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'relationship_to_venue' => ['required', Rule::in([
                'owner',
                'authorized_manager',
                'authorized_representative',
            ])],
            'verification_contact' => ['required', 'string', 'max:160'],
            'evidence_details' => ['required', 'string', 'min:30', 'max:3000'],
        ];
    }
}
