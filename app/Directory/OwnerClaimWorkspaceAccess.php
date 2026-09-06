<?php

namespace App\Directory;

use App\Enums\DirectoryClaimStatus;
use App\Enums\VenueApplicationStatus;
use App\Models\Organization;

class OwnerClaimWorkspaceAccess
{
    /** @return array{restricted: bool, state: string|null, message: string|null} */
    public function status(Organization $organization): array
    {
        if (! $organization->requires_venue_claim_approval) {
            return [
                'restricted' => false,
                'state' => null,
                'message' => null,
            ];
        }

        $latestClaim = $organization->venueClaimRequests()
            ->latest('id')
            ->first(['status']);
        $latestApplication = $organization->venueApplications()
            ->latest('id')
            ->first(['status']);

        if ($latestApplication !== null) {
            [$state, $message] = match ($latestApplication->status) {
                VenueApplicationStatus::Pending => [
                    'under_review',
                    'FinACourt is reviewing your new venue application. Private setup unlocks after ownership approval.',
                ],
                VenueApplicationStatus::Rejected => [
                    'not_approved',
                    'Your venue application needs changes. Update the venue details and save to resubmit it.',
                ],
                VenueApplicationStatus::Approved => [
                    'finishing_approval',
                    'Your venue application was approved and FinACourt is finishing workspace access.',
                ],
            };

            return [
                'restricted' => true,
                'state' => $state,
                'message' => $message,
            ];
        }

        [$state, $message] = match ($latestClaim?->status) {
            DirectoryClaimStatus::Pending => [
                'under_review',
                'FinACourt is reviewing your venue ownership request. Owner tools unlock after the platform approves it.',
            ],
            DirectoryClaimStatus::Rejected => [
                'not_approved',
                'FinACourt could not approve this venue request. Review the reason below before contacting FinACourt about next steps.',
            ],
            DirectoryClaimStatus::Cancelled => [
                'cancelled',
                'This venue ownership request was cancelled. Contact FinACourt if you still need access to the owner workspace.',
            ],
            DirectoryClaimStatus::Approved => [
                'finishing_approval',
                'Your venue was approved and FinACourt is finishing access to the owner workspace.',
            ],
            default => [
                'confirmation_required',
                'Finish venue onboarding to submit or confirm your venue. Owner tools unlock after FinACourt approves ownership.',
            ],
        };

        return [
            'restricted' => true,
            'state' => $state,
            'message' => $message,
        ];
    }

    public function begin(Organization $organization): void
    {
        if ($organization->requires_venue_claim_approval || $organization->venues()->exists()) {
            return;
        }

        $organization->forceFill(['requires_venue_claim_approval' => true])->save();
    }

    public function complete(Organization $organization): void
    {
        if (! $organization->requires_venue_claim_approval) {
            return;
        }

        $organization->forceFill(['requires_venue_claim_approval' => false])->save();
    }
}
