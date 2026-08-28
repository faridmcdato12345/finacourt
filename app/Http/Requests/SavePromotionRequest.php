<?php

namespace App\Http\Requests;

use App\Bookings\AvailabilityService;
use App\Enums\PromotionDiscountType;
use App\Enums\PromotionGoal;
use App\Enums\PromotionStatus;
use App\Enums\PromotionType;
use App\Models\CourtResource;
use App\Models\Promotion;
use App\Models\Venue;
use App\Promotions\PromotionLifecycle;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

class SavePromotionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $promotion = $this->route('promotion');

        if ($promotion instanceof Promotion) {
            return $this->user()?->can('update', $promotion) === true;
        }

        $context = app(TenantContext::class);

        return $context->hasOrganization()
            && $this->user()?->can('manageInventory', $context->organization()) === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $promotion = $this->route('promotion');
        $organizationId = $promotion instanceof Promotion
            ? $promotion->organization_id
            : app(TenantContext::class)->organization()->getKey();

        return [
            'venue_id' => [
                'required',
                'integer',
                Rule::exists('venues', 'id')->where('organization_id', $organizationId),
            ],
            'resource_id' => ['nullable', 'integer', Rule::exists('resources', 'id')],
            'audience_sport_id' => [
                'nullable',
                'integer',
                Rule::exists('sports', 'id')->where('is_active', true),
            ],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'promotion_type' => ['required', Rule::enum(PromotionType::class)],
            'goal' => ['nullable', Rule::enum(PromotionGoal::class)],
            'status' => ['nullable', Rule::enum(PromotionStatus::class)],
            'discount_type' => ['nullable', Rule::enum(PromotionDiscountType::class)],
            'discount_value' => ['nullable', 'numeric', 'between:0,999999.99'],
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
            'days_of_week' => ['nullable', 'array'],
            'days_of_week.*' => ['integer', 'distinct', 'between:0,6'],
            'starts_at_time' => ['nullable', 'date_format:H:i', 'required_with:ends_at_time'],
            'ends_at_time' => ['nullable', 'date_format:H:i', 'required_with:starts_at_time'],
            'slots' => ['nullable', 'array', 'max:60'],
            'slots.*.id' => [
                'nullable',
                'integer',
                Rule::exists('promotion_slots', 'id')->where(
                    'promotion_id',
                    $promotion instanceof Promotion ? $promotion->getKey() : 0,
                ),
            ],
            'slots.*.resource_id' => ['required', 'integer', Rule::exists('resources', 'id')],
            'slots.*.slot_date' => ['required', 'date_format:Y-m-d'],
            'slots.*.starts_at_time' => ['required', 'date_format:H:i'],
            'slots.*.ends_at_time' => ['required', 'date_format:H:i'],
            // is_active remains accepted for backward compatibility with the
            // original form; V2 writes the explicit lifecycle status instead.
            'is_active' => ['nullable', 'boolean'],
            'is_public' => ['required', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $venue = Venue::query()
                ->with(['organization', 'operatingHours'])
                ->find((int) $this->input('venue_id'));
            $resourceId = $this->filled('resource_id') ? (int) $this->input('resource_id') : null;
            $resource = $resourceId ? CourtResource::query()->find($resourceId) : null;
            $type = PromotionType::from($this->string('promotion_type')->toString());
            $discountType = $this->filled('discount_type')
                ? PromotionDiscountType::from($this->string('discount_type')->toString())
                : null;
            $goal = $this->filled('goal')
                ? PromotionGoal::from($this->string('goal')->toString())
                : PromotionGoal::FillEmptySlots;
            $promotion = $this->route('promotion');
            $promotion = $promotion instanceof Promotion ? $promotion : null;
            $lifecycle = app(PromotionLifecycle::class);
            $status = $lifecycle->target($this->all(), $promotion);

            if ($promotion !== null && ! $promotion->status->canTransitionTo($status)) {
                $validator->errors()->add(
                    'status',
                    "A {$promotion->status->label()} campaign cannot move to {$status->label()}.",
                );
            }

            if ($promotion === null && in_array($status, [
                PromotionStatus::Paused,
                PromotionStatus::Completed,
                PromotionStatus::Cancelled,
            ], true)) {
                $validator->errors()->add('status', 'New campaigns must begin as draft, scheduled, or active.');
            }

            if ($status->acceptsBookings()
                && $this->string('ends_on')->toString() < now($venue?->organization?->timezone)->toDateString()) {
                $validator->errors()->add('status', 'An expired campaign cannot be scheduled or active.');
            }

            if ($resource !== null && $resource->venue_id !== $venue?->getKey()) {
                $validator->errors()->add('resource_id', 'The selected resource must belong to the selected venue.');
            }

            if ($type === PromotionType::Resource && $resource === null) {
                $validator->errors()->add('resource_id', 'A resource promotion requires a court or resource.');
            }

            if ($type === PromotionType::Venue && $resource !== null) {
                $validator->errors()->add('resource_id', 'A venue promotion applies to the whole venue.');
            }

            if ($type === PromotionType::TimeWindow && ! $this->filled('starts_at_time')) {
                $validator->errors()->add('starts_at_time', 'A time-window promotion requires start and end times.');
            }

            $slots = $this->input('slots', []);

            if ($type === PromotionType::SpecificSlots && $slots === []) {
                $validator->errors()->add('slots', 'Choose at least one available slot for this campaign type.');
            }

            if ($goal === PromotionGoal::PromoteSpecificSlots && $slots === []) {
                $validator->errors()->add('slots', 'The specific-slot goal requires at least one selected slot.');
            }

            if ($type === PromotionType::Deal && $discountType === null) {
                $validator->errors()->add('discount_type', 'A discount deal requires a discount configuration.');
            }

            if ($discountType !== null && ! $this->filled('discount_value')) {
                $validator->errors()->add('discount_value', 'Enter the percentage or promotional hourly rate.');
            }

            if ($discountType === null && $this->filled('discount_value')) {
                $validator->errors()->add('discount_type', 'Choose how the entered discount value should be applied.');
            }

            if ($discountType === PromotionDiscountType::Percentage
                && (float) $this->input('discount_value') <= 0) {
                $validator->errors()->add('discount_value', 'Percentage discounts must be greater than zero.');
            }

            if ($discountType === PromotionDiscountType::Percentage
                && (float) $this->input('discount_value') > 100) {
                $validator->errors()->add('discount_value', 'Percentage discounts cannot exceed 100%.');
            }

            if ($discountType === PromotionDiscountType::FixedHourlyRate && $venue !== null) {
                $minimumRate = $resource?->base_hourly_rate
                    ?? $venue->resources()->where('is_active', true)->min('base_hourly_rate');

                if ($minimumRate === null) {
                    $validator->errors()->add('resource_id', 'The venue needs an active resource before using a fixed promotional rate.');
                } elseif ((float) $this->input('discount_value') >= (float) $minimumRate) {
                    $validator->errors()->add(
                        'discount_value',
                        'The promotional hourly rate must be lower than every applicable active resource rate.',
                    );
                }
            }

            if ($this->filled('starts_at_time')
                && $this->string('ends_at_time')->toString() <= $this->string('starts_at_time')->toString()) {
                $validator->errors()->add('ends_at_time', 'The applicability end time must be later than its start time.');
            }

            if ($venue !== null && $this->filled('audience_sport_id')
                && ! $venue->resources()
                    ->where('is_active', true)
                    ->where('sport_id', (int) $this->input('audience_sport_id'))
                    ->exists()) {
                $validator->errors()->add('audience_sport_id', 'Choose a sport offered by an active court at this venue.');
            }

            $resources = CourtResource::query()
                ->whereIn('id', collect($slots)->pluck('resource_id')->filter()->map(fn ($id) => (int) $id))
                ->get()
                ->keyBy('id');
            $availability = app(AvailabilityService::class);
            $windows = [];

            foreach ($slots as $index => $slot) {
                $slotResource = $resources->get((int) ($slot['resource_id'] ?? 0));

                if ($slotResource === null || $slotResource->venue_id !== $venue?->getKey()) {
                    $validator->errors()->add(
                        "slots.{$index}.resource_id",
                        'Each promoted slot must belong to the selected venue and tenant.',
                    );

                    continue;
                }

                if ($resource !== null && $slotResource->getKey() !== $resource->getKey()) {
                    $validator->errors()->add(
                        "slots.{$index}.resource_id",
                        'All selected slots must use the campaign court, or choose all venue courts.',
                    );
                }

                $slotDate = (string) ($slot['slot_date'] ?? '');

                if ($slotDate < $this->string('starts_on')->toString()
                    || $slotDate > $this->string('ends_on')->toString()) {
                    $validator->errors()->add(
                        "slots.{$index}.slot_date",
                        'Each selected slot must fall inside the campaign dates.',
                    );
                }

                $start = (string) ($slot['starts_at_time'] ?? '');
                $end = (string) ($slot['ends_at_time'] ?? '');

                if ($end <= $start) {
                    $validator->errors()->add(
                        "slots.{$index}.ends_at_time",
                        'The slot end time must be later than its start time.',
                    );

                    continue;
                }

                $slotResource->setRelation('venue', $venue);

                try {
                    $window = $availability->window(
                        $slotResource,
                        $slotDate,
                        $start,
                        $end,
                        requireFuture: false,
                    );
                    $availability->ensureBookable($slotResource, $window);
                } catch (ValidationException $exception) {
                    $validator->errors()->add(
                        "slots.{$index}.starts_at_time",
                        collect($exception->errors())->flatten()->first(),
                    );

                    continue;
                }

                $key = $slotResource->getKey().'|'.$slotDate;

                foreach ($windows[$key] ?? [] as $existing) {
                    if ($start < $existing['end'] && $end > $existing['start']) {
                        $validator->errors()->add(
                            "slots.{$index}.starts_at_time",
                            'Selected slots for the same court cannot overlap.',
                        );
                    }
                }

                $windows[$key][] = ['start' => $start, 'end' => $end];
            }
        }];
    }
}
