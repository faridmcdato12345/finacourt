<?php

namespace App\Directory;

use App\Enums\DirectoryClaimStatus;
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
                'Confirm the venue from your private invitation to start FinACourt’s ownership review. Owner tools unlock after approval.',
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
