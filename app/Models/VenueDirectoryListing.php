<?php

namespace App\Models;

use App\Enums\DirectoryListingStatus;
use App\Enums\DirectorySourceType;
use Database\Factories\VenueDirectoryListingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'created_by_user_id',
    'verified_by_user_id',
    'rights_confirmed_by_user_id',
    'claimed_venue_id',
    'public_id',
    'directory_key',
    'slug',
    'status',
    'name',
    'description',
    'address',
    'city',
    'city_slug',
    'province',
    'province_slug',
    'psgc_region_code',
    'psgc_province_code',
    'psgc_city_municipality_code',
    'country',
    'latitude',
    'longitude',
    'coordinates_verified_at',
    'phone',
    'email',
    'website',
    'source_type',
    'source_url',
    'source_reference',
    'verification_notes',
    'rights_confirmed_at',
    'last_verified_at',
    'closed_at',
    'claimed_at',
])]
#[Hidden(['directory_key', 'source_reference', 'verification_notes'])]
class VenueDirectoryListing extends Model
{
    /** @use HasFactory<VenueDirectoryListingFactory> */
    use HasFactory;

    /** @param Builder<VenueDirectoryListing> $query */
    public function scopeDiscoverable(Builder $query): void
    {
        $query->where('status', DirectoryListingStatus::Published)
            ->whereNotNull('last_verified_at');
    }

    /** @param Builder<VenueDirectoryListing> $query */
    public function scopePublicPage(Builder $query): void
    {
        $query->whereIn('status', [
            DirectoryListingStatus::Published,
            DirectoryListingStatus::Claimed,
            DirectoryListingStatus::Closed,
        ]);
    }

    public function isClaimable(): bool
    {
        return $this->status === DirectoryListingStatus::Published
            && $this->last_verified_at !== null
            && $this->claimed_venue_id === null;
    }

    public function isIndexable(): bool
    {
        return $this->status === DirectoryListingStatus::Published
            && $this->last_verified_at !== null
            && $this->sports()->where('is_active', true)->exists();
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by_user_id');
    }

    /** @return BelongsTo<Venue, $this> */
    public function claimedVenue(): BelongsTo
    {
        return $this->belongsTo(Venue::class, 'claimed_venue_id');
    }

    /** @return BelongsToMany<Sport, $this> */
    public function sports(): BelongsToMany
    {
        return $this->belongsToMany(Sport::class, 'sport_venue_directory_listing')->withTimestamps();
    }

    /** @return HasMany<VenueDirectoryHour, $this> */
    public function hours(): HasMany
    {
        return $this->hasMany(VenueDirectoryHour::class)->orderBy('day_of_week');
    }

    /** @return HasMany<VenueClaimRequest, $this> */
    public function claimRequests(): HasMany
    {
        return $this->hasMany(VenueClaimRequest::class);
    }

    /** @return HasMany<VenueClaimInvitation, $this> */
    public function claimInvitations(): HasMany
    {
        return $this->hasMany(VenueClaimInvitation::class);
    }

    /** @return HasMany<VenueDirectoryReport, $this> */
    public function reports(): HasMany
    {
        return $this->hasMany(VenueDirectoryReport::class);
    }

    /** @return HasMany<VenueDirectoryAudit, $this> */
    public function audits(): HasMany
    {
        return $this->hasMany(VenueDirectoryAudit::class)->latest('occurred_at');
    }

    /** @return HasMany<AnalyticsEvent, $this> */
    public function analyticsEvents(): HasMany
    {
        return $this->hasMany(AnalyticsEvent::class);
    }

    protected function casts(): array
    {
        return [
            'status' => DirectoryListingStatus::class,
            'source_type' => DirectorySourceType::class,
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'coordinates_verified_at' => 'immutable_datetime',
            'rights_confirmed_at' => 'immutable_datetime',
            'last_verified_at' => 'immutable_datetime',
            'closed_at' => 'immutable_datetime',
            'claimed_at' => 'immutable_datetime',
        ];
    }
}
