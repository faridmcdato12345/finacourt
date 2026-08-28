<?php

namespace App\Models;

use App\Enums\PartnerPayoutStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'sales_partner_profile_id', 'period_started_at', 'period_ended_at', 'amount',
    'currency', 'status', 'approved_by_user_id', 'approved_at', 'paid_by_user_id',
    'paid_at', 'payment_reference', 'note',
])]
class PartnerPayout extends Model
{
    /** @return BelongsTo<SalesPartnerProfile, $this> */
    public function partner(): BelongsTo
    {
        return $this->belongsTo(SalesPartnerProfile::class, 'sales_partner_profile_id');
    }

    /** @return HasMany<CommissionEntry, $this> */
    public function entries(): HasMany
    {
        return $this->hasMany(CommissionEntry::class);
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'status' => PartnerPayoutStatus::class,
            'period_started_at' => 'immutable_date',
            'period_ended_at' => 'immutable_date',
            'approved_at' => 'immutable_datetime',
            'paid_at' => 'immutable_datetime',
        ];
    }
}
