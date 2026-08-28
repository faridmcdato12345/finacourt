<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePlatformServiceFeeRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_platform_admin === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'is_active' => ['required', 'boolean'],
        ];
    }
}
