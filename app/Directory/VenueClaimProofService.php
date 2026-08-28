<?php

namespace App\Directory;

use App\Enums\DirectoryClaimStatus;
use App\Enums\VenueClaimProofMethod;
use App\Enums\VenueClaimProofStatus;
use App\Models\Organization;
use App\Models\User;
use App\Models\VenueClaimRequest;
use App\Notifications\VenueClaimVerificationCode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class VenueClaimProofService
{
    public function __construct(private readonly VenueDirectoryAudit $audit) {}

    public function issuePublicEmailCode(
        VenueClaimRequest $claim,
        User $requester,
        Organization $organization,
    ): void {
        $code = (string) random_int(100000, 999999);
        $minutes = max(5, (int) config('directory.claim_verification_code_minutes', 30));
        $expiresAt = now('UTC')->addMinutes($minutes);

        $delivery = DB::transaction(function () use ($claim, $requester, $organization, $code, $expiresAt): array {
            $locked = VenueClaimRequest::query()
                ->with('listing:id,name,slug,email,last_verified_at')
                ->lockForUpdate()
                ->findOrFail($claim->getKey());
            $this->guardRequester($locked, $requester, $organization);

            if ($locked->proof_status === VenueClaimProofStatus::Locked) {
                throw ValidationException::withMessages([
                    'proof' => 'Email verification is locked after too many attempts. FinACourt must review the request manually.',
                ]);
            }

            $email = $locked->listing->email;

            if (! is_string($email) || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                throw ValidationException::withMessages([
                    'proof' => 'This listing has no independently sourced venue email. FinACourt must verify the request through another official channel.',
                ]);
            }

            $locked->update([
                'proof_status' => VenueClaimProofStatus::Pending,
                'proof_method' => VenueClaimProofMethod::PublicEmailCode,
                'proof_destination' => $this->maskEmail($email),
                'proof_code_hash' => Hash::make($code),
                'proof_code_expires_at' => $expiresAt,
                'proof_attempts' => 0,
                'proof_sent_at' => now('UTC'),
                'proof_verified_at' => null,
                'proof_verified_by_user_id' => null,
                'proof_notes' => null,
                'approval_available_at' => null,
            ]);
            $this->audit->record($locked->listing, 'claim_email_code_sent', $requester, $locked, [
                'destination' => $locked->proof_destination,
                'expires_at' => $expiresAt->toISOString(),
            ]);

            return [
                'email' => $email,
                'venue_name' => $locked->listing->name,
                'listing_slug' => $locked->listing->slug,
            ];
        });

        Notification::route('mail', $delivery['email'])->notify(new VenueClaimVerificationCode(
            $delivery['venue_name'],
            $code,
            $expiresAt->format('M j, Y H:i').' UTC',
            route('marketplace.directory.show', $delivery['listing_slug']),
        ));
    }

    public function verifyPublicEmailCode(
        VenueClaimRequest $claim,
        User $requester,
        Organization $organization,
        string $code,
    ): void {
        $error = DB::transaction(function () use ($claim, $requester, $organization, $code): ?string {
            $locked = VenueClaimRequest::query()
                ->with('listing:id,name')
                ->lockForUpdate()
                ->findOrFail($claim->getKey());
            $this->guardRequester($locked, $requester, $organization);

            if ($locked->proof_method !== VenueClaimProofMethod::PublicEmailCode
                || ! is_string($locked->proof_code_hash)) {
                return 'Request a new code sent to the venue’s public email first.';
            }

            if ($locked->proof_status === VenueClaimProofStatus::Locked) {
                return 'Email verification is locked after too many attempts. FinACourt must review the request manually.';
            }

            if ($locked->proof_code_expires_at === null || $locked->proof_code_expires_at->isPast()) {
                $locked->update([
                    'proof_status' => VenueClaimProofStatus::Pending,
                    'proof_code_hash' => null,
                    'proof_code_expires_at' => null,
                ]);

                return 'That code has expired. Request a new code.';
            }

            $attempts = $locked->proof_attempts + 1;

            if (! Hash::check($code, $locked->proof_code_hash)) {
                $maximumAttempts = max(3, (int) config('directory.claim_verification_max_attempts', 5));
                $locked->update([
                    'proof_attempts' => $attempts,
                    'proof_status' => $attempts >= $maximumAttempts
                        ? VenueClaimProofStatus::Locked
                        : VenueClaimProofStatus::Pending,
                ]);

                if ($attempts >= $maximumAttempts) {
                    $this->audit->record($locked->listing, 'claim_email_code_locked', $requester, $locked);
                }

                return $attempts >= $maximumAttempts
                    ? 'Too many incorrect codes. FinACourt must review the request manually.'
                    : 'That verification code is not correct.';
            }

            $holdUntil = now('UTC')->addHours(
                max(0, (int) config('directory.claim_approval_hold_hours', 24)),
            );
            $locked->update([
                'proof_status' => VenueClaimProofStatus::Verified,
                'proof_verified_by_user_id' => $requester->getKey(),
                'proof_verified_at' => now('UTC'),
                'proof_code_hash' => null,
                'proof_code_expires_at' => null,
                'proof_attempts' => $attempts,
                'approval_available_at' => $holdUntil,
            ]);
            $this->audit->record($locked->listing, 'claim_ownership_proof_verified', $requester, $locked, [
                'method' => VenueClaimProofMethod::PublicEmailCode->value,
                'approval_available_at' => $holdUntil->toISOString(),
            ]);

            return null;
        });

        if ($error !== null) {
            throw ValidationException::withMessages(['code' => $error]);
        }
    }

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

    private function guardRequester(
        VenueClaimRequest $claim,
        User $requester,
        Organization $organization,
    ): void {
        $this->guardPending($claim);

        if ($claim->requester_user_id !== $requester->getKey()
            || $claim->organization_id !== $organization->getKey()) {
            abort(404);
        }
    }

    private function guardPending(VenueClaimRequest $claim): void
    {
        if ($claim->status !== DirectoryClaimStatus::Pending) {
            throw ValidationException::withMessages([
                'proof' => 'This ownership request is no longer waiting for review.',
            ]);
        }
    }

    private function maskEmail(string $email): string
    {
        [$local, $domain] = explode('@', $email, 2);
        $visible = mb_substr($local, 0, min(2, mb_strlen($local)));

        return $visible.str_repeat('•', max(3, mb_strlen($local) - mb_strlen($visible))).'@'.$domain;
    }
}
