<?php

namespace App\Http\Requests;

use App\Enums\PlatformServiceFeeType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePlatformServiceFeeRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_platform_admin === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'fee_type' => ['required', Rule::enum(PlatformServiceFeeType::class)],
            'percentage_rate' => [
                'nullable',
                'required_if:fee_type,'.PlatformServiceFeeType::Percentage->value,
                'numeric',
                'min:0.01',
                'max:100',
            ],
            'fixed_amount' => [
                'nullable',
                'required_if:fee_type,'.PlatformServiceFeeType::Fixed->value,
                'numeric',
                'min:0.01',
                'max:99999999.99',
            ],
            'minimum_fee_amount' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'maximum_fee_amount' => ['nullable', 'numeric', 'min:0', 'max:99999999.99', 'gte:minimum_fee_amount'],
            'currency' => ['required', 'string', 'size:3'],
            'is_active' => ['required', 'boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
        ];
    }
}
