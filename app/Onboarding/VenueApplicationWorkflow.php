<?php

namespace App\Onboarding;

use App\Directory\OwnerClaimWorkspaceAccess;
use App\Directory\VenueClaimNotifier;
use App\Enums\MembershipRole;
use App\Enums\VenueApplicationStatus;
use App\Models\Membership;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueApplication;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class VenueApplicationWorkflow
{
    public function __construct(
        private readonly OwnerClaimWorkspaceAccess $workspaceAccess,
        private readonly VenueApplicationNotifier $notifier,
        private readonly VenueClaimNotifier $venueNotifier,
    ) {}

    public function approve(VenueApplication $application, User $administrator, string $reviewNotes): Venue
    {
        abort_unless($administrator->is_platform_admin, 403);

        $venue = DB::transaction(function () use ($application, $administrator, $reviewNotes): Venue {
            $locked = VenueApplication::query()->lockForUpdate()->findOrFail($application->getKey());
            $this->guardPending($locked);

            $venue = Venue::query()->lockForUpdate()->findOrFail($locked->venue_id);
            $membershipExists = Membership::query()
                ->where('organization_id', $locked->organization_id)
                ->where('user_id', $locked->submitted_by_user_id)
                ->where('role', MembershipRole::Owner)
                ->exists();

            if (! $membershipExists) {
                throw ValidationException::withMessages([
                    'application' => 'The applicant is no longer the owner of this account.',
                ]);
            }

            $locked->update([
                'status' => VenueApplicationStatus::Approved,
                'reviewed_by_user_id' => $administrator->getKey(),
                'reviewed_at' => now('UTC'),
                'review_notes' => Str::limit(trim($reviewNotes), 2000),
            ]);
            $this->workspaceAccess->complete($locked->organization);

            return $venue;
        });

        $this->notifier->reviewed($application);

        return $venue;
    }

    public function reject(VenueApplication $application, User $administrator, string $reviewNotes): void
    {
        abort_unless($administrator->is_platform_admin, 403);

        DB::transaction(function () use ($application, $administrator, $reviewNotes): void {
            $locked = VenueApplication::query()->lockForUpdate()->findOrFail($application->getKey());
            $this->guardPending($locked);
            $locked->update([
                'status' => VenueApplicationStatus::Rejected,
                'reviewed_by_user_id' => $administrator->getKey(),
                'reviewed_at' => now('UTC'),
                'review_notes' => Str::limit(trim($reviewNotes), 2000),
            ]);
        });

        $this->notifier->reviewed($application);
    }

    public function resubmit(VenueApplication $application): void
    {
        DB::transaction(function () use ($application): void {
            $locked = VenueApplication::query()->lockForUpdate()->findOrFail($application->getKey());

            if ($locked->status !== VenueApplicationStatus::Rejected) {
                return;
            }

            $locked->update([
                'status' => VenueApplicationStatus::Pending,
                'submitted_at' => now('UTC'),
                'reviewed_by_user_id' => null,
                'reviewed_at' => null,
                'review_notes' => null,
            ]);
            $locked->organization->forceFill(['requires_venue_claim_approval' => true])->save();
        });

        $this->notifier->submitted($application);
    }

    public function verifyForMarketplace(VenueApplication $application, User $administrator, string $notes): Venue
    {
        abort_unless($administrator->is_platform_admin, 403);

        $venue = DB::transaction(function () use ($application, $notes): Venue {
            $locked = VenueApplication::query()->lockForUpdate()->findOrFail($application->getKey());

            if ($locked->status !== VenueApplicationStatus::Approved) {
                throw ValidationException::withMessages([
                    'application' => 'Ownership must be approved before the final marketplace review.',
                ]);
            }

            $venue = Venue::query()->lockForUpdate()->findOrFail($locked->venue_id);

            if ($venue->verified_at !== null) {
                throw ValidationException::withMessages([
                    'application' => 'This venue has already completed its final marketplace review.',
                ]);
            }

            if (! $venue->is_published
                || $venue->marketplace_review_requested_at === null
                || ! $venue->resources()->marketplace()->exists()) {
                throw ValidationException::withMessages([
                    'application' => 'The owner must finish setup, add an active court, and request publication first.',
                ]);
            }

            $venue->update(['verified_at' => now('UTC')]);

            // The notes stay with the application audit record without
            // overwriting the original ownership decision.
            $locked->update([
                'review_notes' => trim($locked->review_notes."\n\nFinal marketplace review: ".Str::limit(trim($notes), 2000)),
            ]);

            return $venue;
        });

        $this->venueNotifier->marketplaceApproved($venue);

        return $venue;
    }

    private function guardPending(VenueApplication $application): void
    {
        if ($application->status !== VenueApplicationStatus::Pending) {
            throw ValidationException::withMessages([
                'application' => 'This venue application has already been reviewed.',
            ]);
        }
    }
}
