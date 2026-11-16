<?php

namespace App\Http\Requests;

use App\Enums\ReactivationSegment;
use App\Enums\CustomerOfferDiscountType;
use App\Models\CourtResource;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreReactivationCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        $context = app(TenantContext::class);

        return $context->hasOrganization()
            && $this->user()?->can('manageBookings', $context->organization()) === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $organizationId = app(TenantContext::class)->organization()->getKey();

        return [
            'venue_id' => [
                'required',
                'integer',
                Rule::exists('venues', 'id')->where('organization_id', $organizationId),
            ],
            'sport_id' => ['nullable', 'integer', Rule::exists('sports', 'id')->where('is_active', true)],
            'title' => ['required', 'string', 'max:120'],
            'message' => ['required', 'string', 'max:500'],
            'segment' => ['required', Rule::enum(ReactivationSegment::class)],
            'delivery_mode' => ['nullable', Rule::in(['manual', 'automated'])],
            'automation_inactivity_days' => ['nullable', 'required_if:delivery_mode,automated', 'integer', Rule::in([14, 30, 60])],
            'include_exclusive_offer' => ['nullable', 'boolean'],
            'offer_discount_type' => ['nullable', Rule::requiredIf(fn (): bool => $this->boolean('include_exclusive_offer')), Rule::enum(CustomerOfferDiscountType::class)],
            'offer_discount_value' => ['nullable', Rule::requiredIf(fn (): bool => $this->boolean('include_exclusive_offer')), 'decimal:0,2', 'gt:0'],
            'offer_minimum_spend' => ['nullable', 'decimal:0,2', 'gte:0'],
            'offer_valid_days' => ['nullable', Rule::requiredIf(fn (): bool => $this->boolean('include_exclusive_offer')), 'integer', 'between:1,90'],
            'offer_max_redemptions' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $segment = ReactivationSegment::from($this->string('segment')->toString());

            if ($segment === ReactivationSegment::Sport && ! $this->filled('sport_id')) {
                $validator->errors()->add('sport_id', 'Choose a sport for this player group.');

                return;
            }

            if (filter_var($this->input('include_exclusive_offer'), FILTER_VALIDATE_BOOLEAN)
                && $this->input('offer_discount_type') === CustomerOfferDiscountType::Percentage->value
                && (float) $this->input('offer_discount_value', 0) > 100) {
                $validator->errors()->add('offer_discount_value', 'A percentage offer cannot exceed 100%.');
            }

            if ($this->filled('sport_id')) {
                $belongsToVenue = CourtResource::query()
                    ->where('venue_id', $this->integer('venue_id'))
                    ->where('sport_id', $this->integer('sport_id'))
                    ->exists();

                if (! $belongsToVenue) {
                    $validator->errors()->add('sport_id', 'The selected venue must offer this sport.');
                }
            }
        }];
    }
}
