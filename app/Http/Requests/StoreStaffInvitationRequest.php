<?php

namespace App\Http\Requests;

use App\Organizations\StaffPermissionSet;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStaffInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $context = app(TenantContext::class);

        return $context->hasOrganization()
            && $this->user()?->can('manageMembers', $context->organization()) === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255'],
            'permissions' => ['present', 'array', 'max:2'],
            'permissions.*' => ['string', 'distinct', Rule::in(app(StaffPermissionSet::class)->allowedValues())],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => is_string($this->input('email')) ? strtolower(trim($this->input('email'))) : $this->input('email'),
            'permissions' => is_array($this->input('permissions')) ? $this->input('permissions') : [],
        ]);
    }
}
