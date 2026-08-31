<?php

namespace App\Directory;

use App\Enums\DirectoryClaimStatus;
use App\Models\User;
use App\Models\VenueClaimInvitation;
use App\Models\VenueDirectoryListing;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VenueClaimInvitationService
{
    public function __construct(private readonly VenueDirectoryAudit $audit) {}

    /** @return array{invitation: VenueClaimInvitation, token: string} */
    public function issue(VenueDirectoryListing $listing, User $administrator): array
    {
        abort_unless($administrator->is_platform_admin, 403);

        return DB::transaction(function () use ($listing, $administrator): array {
            $locked = VenueDirectoryListing::query()->lockForUpdate()->findOrFail($listing->getKey());

            if (! $locked->isClaimable()) {
                throw ValidationException::withMessages([
                    'invitation' => 'Check and publish this venue before inviting its owner.',
                ]);
            }

            if ($locked->claimRequests()->where('status', DirectoryClaimStatus::Pending)->exists()) {
                throw ValidationException::withMessages([
                    'invitation' => 'This venue already has an ownership request under review.',
                ]);
            }

            $now = now('UTC');
            $locked->claimInvitations()
                ->whereNull('used_at')
                ->whereNull('revoked_at')
                ->update(['revoked_at' => $now, 'updated_at' => $now]);

            $token = bin2hex(random_bytes(32));
            $hours = max(1, (int) config('directory.claim_invitation_hours', 168));
            $invitation = $locked->claimInvitations()->create([
                'created_by_user_id' => $administrator->getKey(),
                'token_hash' => VenueClaimInvitation::hashToken($token),
                'expires_at' => $now->copy()->addHours($hours),
            ]);

            $this->audit->record($locked, 'claim_invitation_created', $administrator, changes: [
                'invitation_id' => $invitation->getKey(),
                'expires_at' => $invitation->expires_at->toIso8601String(),
            ]);

            return ['invitation' => $invitation, 'token' => $token];
        });
    }

    public function resolveUsable(string $token): VenueClaimInvitation
    {
        $invitation = VenueClaimInvitation::query()
            ->with(['listing.sports:id,name'])
            ->where('token_hash', VenueClaimInvitation::hashToken($token))
            ->first();

        abort_unless(
            $invitation?->isUsable() && $invitation->listing->isClaimable(),
            404,
            'This private venue link is invalid or has expired.',
        );

        return $invitation;
    }

    public function revoke(
        VenueDirectoryListing $listing,
        VenueClaimInvitation $invitation,
        User $administrator,
    ): void {
        abort_unless($administrator->is_platform_admin, 403);

        DB::transaction(function () use ($listing, $invitation, $administrator): void {
            $lockedListing = VenueDirectoryListing::query()->lockForUpdate()->findOrFail($listing->getKey());
            $lockedInvitation = VenueClaimInvitation::query()->lockForUpdate()->findOrFail($invitation->getKey());
            abort_unless($lockedInvitation->venue_directory_listing_id === $lockedListing->getKey(), 404);

            if ($lockedInvitation->used_at !== null) {
                throw ValidationException::withMessages([
                    'invitation' => 'This private link was already used and cannot be changed.',
                ]);
            }

            if ($lockedInvitation->revoked_at === null) {
                $lockedInvitation->update(['revoked_at' => now('UTC')]);
                $this->audit->record($lockedListing, 'claim_invitation_revoked', $administrator, changes: [
                    'invitation_id' => $lockedInvitation->getKey(),
                ]);
            }
        });
    }
}
