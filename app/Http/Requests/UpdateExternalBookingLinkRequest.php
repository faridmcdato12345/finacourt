<?php

namespace App\Http\Requests;

use App\Enums\VisibilityLinkDestination;
use App\Models\VisibilityLink;
use Illuminate\Foundation\Http\FormRequest;

class UpdateExternalBookingLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        $link = $this->route('visibilityLink');

        return $link instanceof VisibilityLink
            && $link->destination === VisibilityLinkDestination::ExternalBooking
            && $this->user()?->can('update', $link->venue) === true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('label')) {
            $this->merge(['label' => trim((string) $this->input('label'))]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'label' => ['sometimes', 'required', 'string', 'max:120'],
            'is_active' => ['sometimes', 'required', 'boolean'],
        ];
    }
}
