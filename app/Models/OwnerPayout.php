<?php

namespace App\Models;

use App\Enums\OwnerPayoutStatus;
use App\Enums\OwnerPayoutType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'organization_id', 'reference', 'payout_type', 'status', 'amount',
    'gross_amount', 'payout_fee', 'net_amount', 'fee_payer', 'currency',
    'provider', 'cycle_key', 'period_started_at', 'period_ended_at',
    'scheduled_for', 'destination_snapshot', 'external_reference', 'note',
    'reconciliation_key', 'metadata', 'created_by_user_id', 'approved_by_user_id',
    'requested_by_user_id', 'sent_by_user_id', 'requested_at', 'approved_at',
    'processing_started_at', 'sent_at', 'paid_at', 'paid_amount', 'failed_at',
    'failure_code', 'failure_message', 'reversed_at', 'cancelled_at',
])]
#[Hidden(['destination_snapshot', 'reconciliation_key'])]
class OwnerPayout extends Model
{
    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return HasMany<OwnerSettlementEntry, $this> */
    public function entries(): HasMany
    {
        return $this->hasMany(OwnerSettlementEntry::class);
    }

    /** @return HasMany<OwnerPayoutEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(OwnerPayoutEvent::class)->orderBy('created_at')->orderBy('id');
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    protected function casts(): array
    {
        return [
            'status' => OwnerPayoutStatus::class,
            'payout_type' => OwnerPayoutType::class,
            'amount' => 'decimal:2',
            'gross_amount' => 'decimal:2',
            'payout_fee' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'destination_snapshot' => 'encrypted:array',
            'metadata' => 'array',
            'period_started_at' => 'immutable_date',
            'period_ended_at' => 'immutable_date',
            'scheduled_for' => 'immutable_date',
            'requested_at' => 'immutable_datetime',
            'approved_at' => 'immutable_datetime',
            'processing_started_at' => 'immutable_datetime',
            'sent_at' => 'immutable_datetime',
            'paid_at' => 'immutable_datetime',
            'paid_amount' => 'decimal:2',
            'failed_at' => 'immutable_datetime',
            'reversed_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
        ];
    }
}
