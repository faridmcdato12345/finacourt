<?php

namespace App\Models;

use App\Enums\DirectoryClaimStatus;
use App\Enums\VenueClaimProofMethod;
use App\Enums\VenueClaimProofStatus;
use Database\Factories\VenueClaimRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'venue_directory_listing_id',
    'requester_user_id',
    'organization_id',
    'reviewed_by_user_id',
    'proof_verified_by_user_id',
    'approved_venue_id',
    'status',
    'proof_status',
    'proof_method',
    'proof_destination',
    'proof_code_hash',
    'proof_code_expires_at',
    'proof_attempts',
    'proof_sent_at',
    'proof_verified_at',
    'proof_notes',
    'approval_available_at',
    'active_claim_key',
    'relationship_to_venue',
    'verification_contact',
    'evidence_details',
    'review_notes',
    'reviewed_at',
])]
class VenueClaimRequest extends Model
{
    /** @use HasFactory<VenueClaimRequestFactory> */
    use HasFactory;

    /** @return BelongsTo<VenueDirectoryListing, $this> */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(VenueDirectoryListing::class, 'venue_directory_listing_id');
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_user_id');
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<User, $this> */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function proofVerifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proof_verified_by_user_id');
    }

    public function hasVerifiedOwnershipProof(): bool
    {
        return $this->proof_status === VenueClaimProofStatus::Verified
            && $this->proof_verified_at !== null;
    }

    public function isApprovalAvailable(): bool
    {
        return $this->hasVerifiedOwnershipProof()
            && $this->approval_available_at !== null
            && $this->approval_available_at->isPast();
    }

    /** @return BelongsTo<Venue, $this> */
    public function approvedVenue(): BelongsTo
    {
        return $this->belongsTo(Venue::class, 'approved_venue_id');
    }

    /** @return HasMany<VenueDirectoryAudit, $this> */
    public function audits(): HasMany
    {
        return $this->hasMany(VenueDirectoryAudit::class);
    }

    protected function casts(): array
    {
        return [
            'status' => DirectoryClaimStatus::class,
            'proof_status' => VenueClaimProofStatus::class,
            'proof_method' => VenueClaimProofMethod::class,
            'verification_contact' => 'encrypted',
            'evidence_details' => 'encrypted',
            'proof_code_expires_at' => 'immutable_datetime',
            'proof_attempts' => 'integer',
            'proof_sent_at' => 'immutable_datetime',
            'proof_verified_at' => 'immutable_datetime',
            'proof_notes' => 'encrypted',
            'approval_available_at' => 'immutable_datetime',
            'reviewed_at' => 'immutable_datetime',
        ];
    }
}
