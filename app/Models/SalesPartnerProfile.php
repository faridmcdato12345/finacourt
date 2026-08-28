<?php

namespace App\Models;

use App\Enums\SalesPartnerStatus;
use Database\Factories\SalesPartnerProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id', 'public_id', 'referral_code', 'status', 'payout_details',
    'activated_at', 'suspended_at', 'suspension_reason', 'created_by_user_id',
])]
#[Hidden(['payout_details'])]
class SalesPartnerProfile extends Model
{
    /** @use HasFactory<SalesPartnerProfileFactory> */
    use HasFactory;

    public function isActive(): bool
    {
        return $this->status === SalesPartnerStatus::Active;
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<SalesLead, $this> */
    public function leads(): HasMany
    {
        return $this->hasMany(SalesLead::class);
    }

    /** @return HasMany<SalesPartnerAttribution, $this> */
    public function attributions(): HasMany
    {
        return $this->hasMany(SalesPartnerAttribution::class);
    }

    /** @return HasMany<CommissionEntry, $this> */
    public function commissionEntries(): HasMany
    {
        return $this->hasMany(CommissionEntry::class);
    }

    /** @return HasMany<PartnerPayout, $this> */
    public function payouts(): HasMany
    {
        return $this->hasMany(PartnerPayout::class);
    }

    protected function casts(): array
    {
        return [
            'status' => SalesPartnerStatus::class,
            'payout_details' => 'encrypted:array',
            'activated_at' => 'immutable_datetime',
            'suspended_at' => 'immutable_datetime',
        ];
    }
}
