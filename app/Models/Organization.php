<?php

namespace App\Models;

use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['name', 'slug', 'timezone'])]
class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use HasFactory;

    /** @return HasMany<Membership, $this> */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    /** @return BelongsToMany<User, $this> */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'memberships')
            ->withPivot(['id', 'role', 'permissions', 'joined_at'])
            ->withTimestamps();
    }

    /** @return HasMany<Venue, $this> */
    public function venues(): HasMany
    {
        return $this->hasMany(Venue::class);
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

    /** @return HasMany<AnalyticsEvent, $this> */
    public function analyticsEvents(): HasMany
    {
        return $this->hasMany(AnalyticsEvent::class);
    }

    /** @return HasMany<ReactivationCampaign, $this> */
    public function reactivationCampaigns(): HasMany
    {
        return $this->hasMany(ReactivationCampaign::class);
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

    /** @return HasMany<VenueClaimRequest, $this> */
    public function venueClaimRequests(): HasMany
    {
        return $this->hasMany(VenueClaimRequest::class);
    }

    public function payoutProfile(): HasOne
    {
        return $this->hasOne(OwnerPayoutProfile::class);
    }

    /** @return HasMany<OwnerSettlementEntry, $this> */
    public function settlementEntries(): HasMany
    {
        return $this->hasMany(OwnerSettlementEntry::class);
    }

    /** @return HasMany<OwnerPayout, $this> */
    public function ownerPayouts(): HasMany
    {
        return $this->hasMany(OwnerPayout::class);
    }
}
