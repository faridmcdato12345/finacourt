<?php

namespace App\Directory;

use App\Enums\DirectoryClaimStatus;
use App\Enums\VenueClaimProofMethod;
use App\Enums\VenueClaimProofStatus;
use App\Models\User;
use App\Models\VenueClaimRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VenueClaimProofService
{
    public function __construct(private readonly VenueDirectoryAudit $audit) {}

    public function recordManualVerification(
        VenueClaimRequest $claim,
        User $administrator,
        VenueClaimProofMethod $method,
        string $notes,
    ): void {
        abort_unless($administrator->is_platform_admin, 403);

        if (! $method->isManualReview()) {
            throw ValidationException::withMessages([
                'proof_method' => 'Select an independent verification method.',
            ]);
        }

        DB::transaction(function () use ($claim, $administrator, $method, $notes): void {
            $locked = VenueClaimRequest::query()
                ->with('listing:id,name')
                ->lockForUpdate()
                ->findOrFail($claim->getKey());
            $this->guardPending($locked);
            $holdUntil = now('UTC')->addHours(
                max(0, (int) config('directory.claim_approval_hold_hours', 24)),
            );
            $locked->update([
                'proof_status' => VenueClaimProofStatus::Verified,
                'proof_method' => $method,
                'proof_destination' => null,
                'proof_code_hash' => null,
                'proof_code_expires_at' => null,
                'proof_verified_by_user_id' => $administrator->getKey(),
                'proof_verified_at' => now('UTC'),
                'proof_notes' => $notes,
                'approval_available_at' => $holdUntil,
            ]);
            $this->audit->record($locked->listing, 'claim_ownership_proof_verified', $administrator, $locked, [
                'method' => $method->value,
                'approval_available_at' => $holdUntil->toISOString(),
            ]);
        });
    }

    private function guardPending(VenueClaimRequest $claim): void
    {
        if ($claim->status !== DirectoryClaimStatus::Pending) {
            throw ValidationException::withMessages([
                'proof' => 'This ownership request is no longer waiting for review.',
            ]);
        }
    }
}
