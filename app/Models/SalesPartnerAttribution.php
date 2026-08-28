<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable([
    'sales_partner_profile_id', 'sales_lead_id', 'organization_id', 'venue_id',
    'owner_user_id', 'referral_code_snapshot', 'source', 'attributed_at',
    'activated_at', 'created_by_user_id',
])]
class SalesPartnerAttribution extends Model
{
    protected static function booted(): void
    {
        static::updating(function (self $attribution): void {
            $allowed = ['sales_lead_id', 'venue_id', 'activated_at', 'updated_at'];

            if (array_diff(array_keys($attribution->getDirty()), $allowed) !== []) {
                throw new LogicException('Partner attribution identity is immutable.');
            }
        });
        static::deleting(fn () => throw new LogicException('Partner attributions cannot be deleted directly.'));
    }

    /** @return BelongsTo<SalesPartnerProfile, $this> */
    public function partner(): BelongsTo
    {
        return $this->belongsTo(SalesPartnerProfile::class, 'sales_partner_profile_id');
    }

    /** @return BelongsTo<SalesLead, $this> */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(SalesLead::class, 'sales_lead_id');
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<Venue, $this> */
    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    protected function casts(): array
    {
        return [
            'attributed_at' => 'immutable_datetime',
            'activated_at' => 'immutable_datetime',
        ];
    }
}
