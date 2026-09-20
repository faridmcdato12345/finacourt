<?php

namespace App\Http\Requests;

use App\Organizations\StaffPermissionSet;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStaffMembershipRequest extends FormRequest
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
            'permissions' => ['present', 'array', 'max:2'],
            'permissions.*' => ['string', 'distinct', Rule::in(app(StaffPermissionSet::class)->allowedValues())],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'permissions' => is_array($this->input('permissions')) ? $this->input('permissions') : [],
        ]);
    }
}
