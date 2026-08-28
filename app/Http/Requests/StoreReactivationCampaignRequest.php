<?php

namespace App\Http\Requests;

use App\Enums\ReactivationSegment;
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
                $validator->errors()->add('sport_id', 'Choose a sport for this customer segment.');

                return;
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
