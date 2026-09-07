<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'organization_id',
    'venue_id',
    'resource_id',
    'starts_at',
    'ends_at',
    'timezone',
    'is_all_day',
    'reason',
    'series_token',
    'created_by_user_id',
    'cancelled_at',
    'cancelled_by_user_id',
])]
class CourtAvailabilityBlock extends Model
{
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

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_user_id');
    }

    /** @param Builder<CourtAvailabilityBlock> $query */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('cancelled_at');
    }

    /** @param Builder<CourtAvailabilityBlock> $query */
    public function scopeOverlapping(
        Builder $query,
        CarbonInterface $startsAt,
        CarbonInterface $endsAt,
    ): void {
        $query->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt);
    }

    protected function casts(): array
    {
        return [
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'is_all_day' => 'boolean',
            'cancelled_at' => 'immutable_datetime',
        ];
    }
}
