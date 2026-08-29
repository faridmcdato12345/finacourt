<?php

namespace App\Models;

use App\Enums\OwnerPayoutStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'organization_id', 'reference', 'status', 'amount', 'currency',
    'period_started_at', 'period_ended_at', 'destination_snapshot',
    'external_reference', 'note', 'created_by_user_id', 'approved_by_user_id',
    'requested_by_user_id', 'sent_by_user_id', 'requested_at', 'approved_at',
    'sent_at', 'failed_at', 'reversed_at', 'cancelled_at',
])]
#[Hidden(['destination_snapshot'])]
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
            'amount' => 'decimal:2',
            'destination_snapshot' => 'encrypted:array',
            'period_started_at' => 'immutable_date',
            'period_ended_at' => 'immutable_date',
            'requested_at' => 'immutable_datetime',
            'approved_at' => 'immutable_datetime',
            'sent_at' => 'immutable_datetime',
            'failed_at' => 'immutable_datetime',
            'reversed_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
        ];
    }
}
