<?php

namespace App\Models;

use App\Enums\LeadConflictStatus;
use App\Enums\SalesLeadStatus;
use Database\Factories\SalesLeadFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'sales_partner_profile_id', 'assigned_by_user_id', 'business_name',
    'contact_person', 'contact_method', 'contact_value', 'dedupe_hash', 'city',
    'notes', 'lead_source', 'status', 'conflict_status', 'duplicate_of_lead_id',
    'protection_started_at', 'protection_expires_at', 'onboarding_data',
    'organization_id', 'venue_id', 'owner_user_id', 'activated_at', 'won_at',
    'lost_at', 'expired_at', 'status_changed_at',
])]
class SalesLead extends Model
{
    /** @use HasFactory<SalesLeadFactory> */
    use HasFactory;

    public function isProtected(): bool
    {
        return $this->conflict_status !== LeadConflictStatus::Disputed
            && $this->protection_expires_at?->isFuture()
            && ! in_array($this->status, [SalesLeadStatus::Lost, SalesLeadStatus::Expired], true);
    }

    /** @return BelongsTo<SalesPartnerProfile, $this> */
    public function partner(): BelongsTo
    {
        return $this->belongsTo(SalesPartnerProfile::class, 'sales_partner_profile_id');
    }

    /** @return BelongsTo<SalesLead, $this> */
    public function duplicateOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'duplicate_of_lead_id');
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

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /** @return HasOne<SalesPartnerAttribution, $this> */
    public function attribution(): HasOne
    {
        return $this->hasOne(SalesPartnerAttribution::class);
    }

    protected function casts(): array
    {
        return [
            'contact_value' => 'encrypted',
            'status' => SalesLeadStatus::class,
            'conflict_status' => LeadConflictStatus::class,
            'onboarding_data' => 'array',
            'protection_started_at' => 'immutable_datetime',
            'protection_expires_at' => 'immutable_datetime',
            'activated_at' => 'immutable_datetime',
            'won_at' => 'immutable_datetime',
            'lost_at' => 'immutable_datetime',
            'expired_at' => 'immutable_datetime',
            'status_changed_at' => 'immutable_datetime',
        ];
    }
}
