<?php

namespace App\Models;

use App\Enums\ResourceSetting;
use App\Enums\ResourceType;
use Database\Factories\CourtResourceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'venue_id',
    'sport_id',
    'name',
    'resource_type',
    'setting',
    'is_active',
    'base_hourly_rate',
    'currency',
    'booking_increment_minutes',
])]
class CourtResource extends Model
{
    /** @use HasFactory<CourtResourceFactory> */
    use HasFactory;

    protected $table = 'resources';

    /** @param Builder<CourtResource> $query */
    public function scopeMarketplace(Builder $query): void
    {
        $query->where('is_active', true)
            ->whereHas('sport', fn (Builder $query) => $query->where('is_active', true));
    }

    /** @return BelongsTo<Venue, $this> */
    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    /** @return BelongsTo<Sport, $this> */
    public function sport(): BelongsTo
    {
        return $this->belongsTo(Sport::class);
    }

    /** @return HasMany<Booking, $this> */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'resource_id');
    }

    /** @return HasMany<Promotion, $this> */
    public function promotions(): HasMany
    {
        return $this->hasMany(Promotion::class, 'resource_id');
    }

    /** @return HasMany<PromotionSlot, $this> */
    public function promotionSlots(): HasMany
    {
        return $this->hasMany(PromotionSlot::class, 'resource_id');
    }

    protected function casts(): array
    {
        return [
            'resource_type' => ResourceType::class,
            'setting' => ResourceSetting::class,
            'is_active' => 'boolean',
            'base_hourly_rate' => 'decimal:2',
            'booking_increment_minutes' => 'integer',
        ];
    }
}
