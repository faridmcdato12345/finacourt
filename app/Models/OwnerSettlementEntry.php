<?php

namespace App\Models;

use App\Enums\OwnerSettlementEntryType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'organization_id', 'payment_id', 'booking_id', 'owner_payout_id', 'type',
    'amount', 'currency', 'source_key', 'description', 'occurred_at',
    'available_at', 'metadata', 'created_by_user_id',
])]
class OwnerSettlementEntry extends Model
{
    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<Payment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /** @return BelongsTo<Booking, $this> */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /** @return BelongsTo<OwnerPayout, $this> */
    public function payout(): BelongsTo
    {
        return $this->belongsTo(OwnerPayout::class, 'owner_payout_id');
    }

    protected function casts(): array
    {
        return [
            'type' => OwnerSettlementEntryType::class,
            'amount' => 'decimal:2',
            'occurred_at' => 'immutable_datetime',
            'available_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }
}
