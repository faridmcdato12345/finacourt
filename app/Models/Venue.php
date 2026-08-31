<?php

namespace App\Models;

use Database\Factories\VenueFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'organization_id',
    'name',
    'slug',
    'description',
    'address',
    'city',
    'city_slug',
    'province',
    'province_slug',
    'psgc_region_code',
    'psgc_province_code',
    'psgc_city_municipality_code',
    'latitude',
    'longitude',
    'coordinates_source',
    'coordinates_verified_at',
    'phone',
    'email',
    'website',
    'is_published',
    'claimed_at',
    'verified_at',
])]
class Venue extends Model
{
    /** @use HasFactory<VenueFactory> */
    use HasFactory;

    /** @param Builder<Venue> $query */
    public function scopeMarketplace(Builder $query): void
    {
        $query->where('is_published', true)
            ->whereNotNull('city_slug')
            ->where('city_slug', '!=', '')
            // A directory claim never becomes bookable merely because an
            // owner configures inventory. Platform verification is a separate
            // gate that owners cannot set through venue forms.
            ->where(function (Builder $query): void {
                $query->whereDoesntHave('claimedDirectoryListings')
                    ->orWhereNotNull('verified_at');
            })
            ->whereHas('resources', fn (Builder $query) => $query->marketplace());
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsToMany<Sport, $this> */
    public function sports(): BelongsToMany
    {
        return $this->belongsToMany(Sport::class)->withTimestamps();
    }

    /** @return BelongsToMany<Amenity, $this> */
    public function amenities(): BelongsToMany
    {
        return $this->belongsToMany(Amenity::class)->withTimestamps();
    }

    /** @return HasMany<OperatingHour, $this> */
    public function operatingHours(): HasMany
    {
        return $this->hasMany(OperatingHour::class)->orderBy('day_of_week');
    }

    /** @return HasMany<CourtResource, $this> */
    public function resources(): HasMany
    {
        return $this->hasMany(CourtResource::class);
    }

    /** @return HasMany<VenuePhoto, $this> */
    public function photos(): HasMany
    {
        return $this->hasMany(VenuePhoto::class)
            ->orderByDesc('is_primary')
            ->orderBy('sort_order');
    }

    /** @return HasMany<Booking, $this> */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /** @return HasMany<Promotion, $this> */
    public function promotions(): HasMany
    {
        return $this->hasMany(Promotion::class);
    }

    /** @return HasMany<VenueReview, $this> */
    public function reviews(): HasMany
    {
        return $this->hasMany(VenueReview::class);
    }

    /** @return HasMany<AnalyticsEvent, $this> */
    public function analyticsEvents(): HasMany
    {
        return $this->hasMany(AnalyticsEvent::class);
    }

    /** @return HasMany<VisibilityLink, $this> */
    public function visibilityLinks(): HasMany
    {
        return $this->hasMany(VisibilityLink::class);
    }

    /** @return HasOne<GoogleBusinessProfileConnection, $this> */
    public function googleBusinessProfileConnection(): HasOne
    {
        return $this->hasOne(GoogleBusinessProfileConnection::class);
    }

    /** @return HasMany<GoogleBusinessProfileAudit, $this> */
    public function googleBusinessProfileAudits(): HasMany
    {
        return $this->hasMany(GoogleBusinessProfileAudit::class);
    }

    /** @return HasMany<SalesLead, $this> */
    public function salesLeads(): HasMany
    {
        return $this->hasMany(SalesLead::class);
    }

    /** @return HasMany<GrowthRecommendationState, $this> */
    public function growthRecommendationStates(): HasMany
    {
        return $this->hasMany(GrowthRecommendationState::class);
    }

    /** @return HasMany<VenueDirectoryListing, $this> */
    public function claimedDirectoryListings(): HasMany
    {
        return $this->hasMany(VenueDirectoryListing::class, 'claimed_venue_id');
    }

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'coordinates_verified_at' => 'immutable_datetime',
            'google_place_id_verified_at' => 'immutable_datetime',
            'is_published' => 'boolean',
            'claimed_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }
}
