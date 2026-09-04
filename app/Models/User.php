<?php

namespace App\Models;

use App\Notifications\QueuedVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'email_verified_at', 'password', 'is_platform_admin'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, MustVerifyEmailTrait, Notifiable;

    /** @return HasMany<Membership, $this> */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    /** @return HasMany<SocialAccount, $this> */
    public function socialAccounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class);
    }

    /** @return HasMany<Booking, $this> */
    public function playerBookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'player_user_id');
    }

    /** @return HasMany<VenueReview, $this> */
    public function venueReviews(): HasMany
    {
        return $this->hasMany(VenueReview::class, 'player_user_id');
    }

    /** @return HasOne<MarketingPreference, $this> */
    public function marketingPreference(): HasOne
    {
        return $this->hasOne(MarketingPreference::class);
    }

    /** @return HasOne<SalesPartnerProfile, $this> */
    public function salesPartnerProfile(): HasOne
    {
        return $this->hasOne(SalesPartnerProfile::class);
    }

    /** @return HasMany<ReactivationCampaignRecipient, $this> */
    public function reactivationCampaignRecipients(): HasMany
    {
        return $this->hasMany(ReactivationCampaignRecipient::class);
    }

    /** @return HasMany<VenueClaimRequest, $this> */
    public function venueClaimRequests(): HasMany
    {
        return $this->hasMany(VenueClaimRequest::class, 'requester_user_id');
    }

    /** @return BelongsToMany<Organization, $this> */
    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'memberships')
            ->withPivot(['id', 'role', 'permissions', 'joined_at'])
            ->withTimestamps();
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new QueuedVerifyEmail);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_platform_admin' => 'boolean',
        ];
    }
}
