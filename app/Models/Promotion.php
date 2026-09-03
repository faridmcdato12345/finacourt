<?php

namespace App\Models;

use App\Enums\PromotionDiscountType;
use App\Enums\PromotionGoal;
use App\Enums\PromotionStatus;
use App\Enums\PromotionType;
use Carbon\CarbonInterface;
use Database\Factories\PromotionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'organization_id',
    'venue_id',
    'resource_id',
    'audience_sport_id',
    'audience_city_slug',
    'campaign_token',
    'title',
    'description',
    'promotion_type',
    'goal',
    'status',
    'discount_type',
    'discount_value',
    'starts_on',
    'ends_on',
    'targets_specific_slots',
    'days_of_week',
    'starts_at_time',
    'ends_at_time',
    'is_active',
    'is_public',
    'impressions_count',
    'clicks_count',
    'booking_starts_count',
])]
class Promotion extends Model
{
    /** @use HasFactory<PromotionFactory> */
    use HasFactory;

    /** @param Builder<Promotion> $query */
    public function scopePublicInventory(Builder $query): void
    {
        $query->where('is_active', true)
            ->whereIn('status', [PromotionStatus::Scheduled, PromotionStatus::Active])
            ->where('is_public', true)
            // Coarse UTC bounds keep expired/future rows out of marketplace
            // queries; isPublicNow performs the exact venue-timezone check.
            ->whereDate('starts_on', '<=', now()->addDay()->toDateString())
            ->whereDate('ends_on', '>=', now()->subDay()->toDateString())
            ->whereHas('venue', fn (Builder $query) => $query
                ->marketplace()
                ->whereColumn('venues.organization_id', 'promotions.organization_id'))
            ->where(function (Builder $query): void {
                $query->whereNull('resource_id')
                    ->orWhereHas('resource', fn (Builder $query) => $query
                        ->marketplace()
                        ->whereColumn('resources.venue_id', 'promotions.venue_id'));
            })
            ->where(function (Builder $query): void {
                $query->where('targets_specific_slots', false)
                    ->orWhereHas('slots', fn (Builder $query) => $query
                        ->whereDate('slot_date', '>=', now()->subDay()->toDateString())
                        ->whereHas('resource', fn (Builder $query) => $query
                            ->marketplace()
                            ->whereColumn('resources.venue_id', 'promotions.venue_id')));
            });
    }

    public function appliesTo(CourtResource $resource, CarbonInterface $localStart, CarbonInterface $localEnd): bool
    {
        if (! $this->is_active
            || ! $this->status->acceptsBookings()
            || $this->organization_id !== $resource->venue->organization_id
            || $this->venue_id !== $resource->venue_id
            || ($this->resource_id !== null && $this->resource_id !== $resource->getKey())
            || ($this->audience_sport_id !== null && $this->audience_sport_id !== $resource->sport_id)) {
            return false;
        }

        $date = $localStart->toDateString();

        if ($date < $this->starts_on->toDateString() || $date > $this->ends_on->toDateString()) {
            return false;
        }

        if ($this->targets_specific_slots) {
            $slots = $this->relationLoaded('slots')
                ? $this->slots
                : $this->slots()->get();

            return $slots->contains(
                fn (PromotionSlot $slot) => $slot->contains($resource, $localStart, $localEnd),
            );
        }

        if ($this->days_of_week !== null && ! in_array($localStart->dayOfWeek, $this->days_of_week, true)) {
            return false;
        }

        if ($this->starts_at_time !== null && $this->ends_at_time !== null) {
            $startTime = $localStart->format('H:i:s');
            $endTime = $localEnd->format('H:i:s');

            if ($startTime < $this->starts_at_time || $endTime > $this->ends_at_time) {
                return false;
            }
        }

        return true;
    }

    public function isPublicNow(?CarbonInterface $at = null): bool
    {
        if (! $this->is_active || ! $this->is_public || ! $this->relationLoaded('venue')) {
            return false;
        }

        $at ??= now($this->venue->organization->timezone);
        $date = $at->toDateString();

        return $this->effectiveStatus($at) === PromotionStatus::Active
            && $this->venue->is_published
            && ($this->resource === null || $this->resource->is_active)
            && $date >= $this->starts_on->toDateString()
            && $date <= $this->ends_on->toDateString()
            && (! $this->targets_specific_slots || $this->nextSlot($at) !== null);
    }

    public function effectiveStatus(?CarbonInterface $at = null): PromotionStatus
    {
        if (! $this->status->acceptsBookings()) {
            return $this->status;
        }

        if ($at === null) {
            $timezone = $this->relationLoaded('venue')
                && $this->venue->relationLoaded('organization')
                ? $this->venue->organization->timezone
                : config('app.timezone');
            $at = now($timezone);
        }

        $date = $at->toDateString();

        if ($date < $this->starts_on->toDateString()) {
            return PromotionStatus::Scheduled;
        }

        if ($date > $this->ends_on->toDateString()) {
            return PromotionStatus::Completed;
        }

        return PromotionStatus::Active;
    }

    public function nextSlot(?CarbonInterface $at = null): ?PromotionSlot
    {
        if (! $this->targets_specific_slots) {
            return null;
        }

        $at ??= $this->relationLoaded('venue') && $this->venue->relationLoaded('organization')
            ? now($this->venue->organization->timezone)
            : now();
        $slots = $this->relationLoaded('slots')
            ? $this->slots
            : $this->slots()->with('resource')->get();

        return $slots
            ->filter(fn (PromotionSlot $slot) => $slot->slot_date->toDateString() > $at->toDateString()
                || ($slot->slot_date->toDateString() === $at->toDateString()
                    && $slot->ends_at_time > $at->format('H:i:s')))
            ->sortBy(fn (PromotionSlot $slot) => $slot->slot_date->format('Y-m-d').' '.$slot->starts_at_time)
            ->first();
    }

    /** @return array<string, int|string> */
    public function marketplaceParameters(): array
    {
        $slot = $this->nextSlot();

        return array_filter([
            'resource' => $slot?->resource_id ?? $this->resource_id,
            'date' => $slot?->slot_date->toDateString(),
            'campaign' => $this->campaign_token,
            'slot' => $slot?->slot_token,
        ]);
    }

    public function marketplaceRankKey(?CarbonInterface $at = null): string
    {
        $at ??= $this->relationLoaded('venue') && $this->venue->relationLoaded('organization')
            ? now($this->venue->organization->timezone)
            : now();
        $slot = $this->nextSlot($at);
        $specificRank = match (true) {
            $slot !== null && $slot->slot_date->toDateString() === $at->toDateString() => 0,
            $slot !== null => 1,
            $this->discount_type !== null => 2,
            default => 3,
        };

        return sprintf(
            '%d|%s|%s|%020d',
            $specificRank,
            $slot?->slot_date->format('Y-m-d').' '.$slot?->starts_at_time,
            $this->ends_on->format('Y-m-d'),
            PHP_INT_MAX - $this->getKey(),
        );
    }

    public function offerLabel(): ?string
    {
        return match ($this->discount_type) {
            PromotionDiscountType::Percentage => rtrim(rtrim($this->discount_value, '0'), '.').'% off',
            PromotionDiscountType::FixedHourlyRate => '₱'.number_format((float) $this->discount_value, 2).'/hour',
            null => null,
        };
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<Venue, $this> */
    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    /** @return BelongsTo<CourtResource, $this> */
    public function resource(): BelongsTo
    {
        return $this->belongsTo(CourtResource::class, 'resource_id');
    }

    /** @return BelongsTo<Sport, $this> */
    public function audienceSport(): BelongsTo
    {
        return $this->belongsTo(Sport::class, 'audience_sport_id');
    }

    /** @return HasMany<PromotionSlot, $this> */
    public function slots(): HasMany
    {
        return $this->hasMany(PromotionSlot::class)->orderBy('slot_date')->orderBy('starts_at_time');
    }

    /** @return HasMany<Booking, $this> */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /** @return HasMany<AnalyticsEvent, $this> */
    public function analyticsEvents(): HasMany
    {
        return $this->hasMany(AnalyticsEvent::class);
    }

    protected function casts(): array
    {
        return [
            'promotion_type' => PromotionType::class,
            'goal' => PromotionGoal::class,
            'status' => PromotionStatus::class,
            'discount_type' => PromotionDiscountType::class,
            'discount_value' => 'decimal:2',
            'starts_on' => 'immutable_date',
            'ends_on' => 'immutable_date',
            'targets_specific_slots' => 'boolean',
            'days_of_week' => 'array',
            'is_active' => 'boolean',
            'is_public' => 'boolean',
            'impressions_count' => 'integer',
            'clicks_count' => 'integer',
            'booking_starts_count' => 'integer',
        ];
    }
}
