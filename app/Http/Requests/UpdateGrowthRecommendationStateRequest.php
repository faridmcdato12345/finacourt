<?php

namespace App\Http\Requests;

use App\Enums\GrowthRecommendationStateStatus;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGrowthRecommendationStateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $context = app(TenantContext::class);

        return $context->hasOrganization()
            && $this->user()?->can('viewDashboard', $context->organization()) === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(GrowthRecommendationStateStatus::class)],
            'snooze_days' => [
                Rule::requiredIf($this->string('status')->toString() === GrowthRecommendationStateStatus::Snoozed->value),
                'nullable',
                'integer',
                Rule::in(config('growth.snooze_days', [7, 30])),
            ],
        ];
    }
}
