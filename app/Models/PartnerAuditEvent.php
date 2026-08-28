<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use LogicException;

#[Fillable([
    'actor_user_id', 'sales_partner_profile_id', 'sales_lead_id',
    'commission_entry_id', 'partner_payout_id', 'action', 'metadata',
])]
class PartnerAuditEvent extends Model
{
    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Partner audit events are immutable.'));
        static::deleting(fn () => throw new LogicException('Partner audit events cannot be deleted.'));
    }

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }
}
