<?php

namespace App\Models;

use App\Enums\CommissionEntryStatus;
use Database\Factories\CommissionEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable([
    'sales_partner_profile_id', 'commission_rule_id', 'sales_lead_id',
    'sales_partner_attribution_id', 'payment_id', 'partner_payout_id',
    'reverses_entry_id', 'source_type', 'source_reference', 'idempotency_key',
    'amount', 'currency', 'status', 'reason', 'rule_snapshot', 'available_at',
    'approved_by_user_id', 'reversed_at', 'reversed_by_user_id',
])]
class CommissionEntry extends Model
{
    /** @use HasFactory<CommissionEntryFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::updating(function (self $entry): void {
            $allowed = [
                'status', 'partner_payout_id', 'available_at', 'approved_by_user_id',
                'reversed_at', 'reversed_by_user_id', 'updated_at',
            ];

            if (array_diff(array_keys($entry->getDirty()), $allowed) !== []) {
                throw new LogicException('Commission ledger facts are immutable; create an adjustment instead.');
            }
        });
        static::deleting(fn () => throw new LogicException('Commission ledger entries cannot be deleted directly.'));
    }

    /** @return BelongsTo<SalesPartnerProfile, $this> */
    public function partner(): BelongsTo
    {
        return $this->belongsTo(SalesPartnerProfile::class, 'sales_partner_profile_id');
    }

    /** @return BelongsTo<CommissionRule, $this> */
    public function rule(): BelongsTo
    {
        return $this->belongsTo(CommissionRule::class, 'commission_rule_id');
    }

    /** @return BelongsTo<SalesLead, $this> */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(SalesLead::class, 'sales_lead_id');
    }

    /** @return BelongsTo<PartnerPayout, $this> */
    public function payout(): BelongsTo
    {
        return $this->belongsTo(PartnerPayout::class, 'partner_payout_id');
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'status' => CommissionEntryStatus::class,
            'rule_snapshot' => 'array',
            'available_at' => 'immutable_datetime',
            'reversed_at' => 'immutable_datetime',
        ];
    }
}
