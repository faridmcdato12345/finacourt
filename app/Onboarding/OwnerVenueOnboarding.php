<?php

namespace App\Onboarding;

use App\Enums\DirectoryClaimStatus;
use App\Enums\VenueApplicationStatus;
use App\Models\Organization;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueApplication;
use App\Models\VenueClaimInvitation;
use App\Models\VenueClaimRequest;
use Illuminate\Support\Collection;

class OwnerVenueOnboarding
{
    /**
     * Build a resumable onboarding state from records the application already
     * trusts. No separate client-side step counter can unlock owner access.
     *
     * @return array<string, mixed>
     */
    public function data(
        User $user,
        Organization $organization,
        ?VenueClaimInvitation $invitation = null,
        ?string $invitationToken = null,
    ): array {
        $claims = $organization->venueClaimRequests()
            ->with(['listing:id,name,slug,address,city,province', 'approvedVenue:id,name,slug'])
            ->latest()
            ->get();
        $latestClaim = $claims->first();

        $venues = $organization->venues()
            ->with('application:id,venue_id,status,submitted_at,review_notes')
            ->withExists('claimedDirectoryListings as requires_claim_review')
            ->withCount([
                'resources',
                'resources as active_resources_count' => fn ($query) => $query->marketplace(),
            ])
            ->latest('id')
            ->get();

        $source = $this->source($invitation, $claims, $venues);
        $venue = $this->currentVenue($venues, $latestClaim);
        $stage = $this->stage($user, $invitation, $latestClaim, $venue);
        $steps = $this->steps($source, $stage);

        return [
            'source' => $source,
            'source_label' => $source === 'invited' ? 'Private venue invitation' : 'New venue setup',
            'stage' => $stage,
            'completed_steps' => collect($steps)->where('state', 'complete')->count(),
            'total_steps' => count($steps),
            'steps' => $steps,
            'claim' => $latestClaim instanceof VenueClaimRequest ? $this->claimData($latestClaim) : null,
            'application' => $venue?->application instanceof VenueApplication
                ? $this->applicationData($venue->application)
                : null,
            'venue' => $venue instanceof Venue ? $this->venueData($venue) : null,
            'invitation' => $invitation instanceof VenueClaimInvitation && is_string($invitationToken)
                ? $this->invitationData($invitation, $invitationToken)
                : null,
        ];
    }

    /** @param Collection<int, VenueClaimRequest> $claims @param Collection<int, Venue> $venues */
    private function source(?VenueClaimInvitation $invitation, Collection $claims, Collection $venues): string
    {
        return $invitation !== null
            || $claims->isNotEmpty()
            || $venues->contains(fn (Venue $venue) => (bool) $venue->requires_claim_review)
                ? 'invited'
                : 'self_service';
    }

    /** @param Collection<int, Venue> $venues */
    private function currentVenue(Collection $venues, mixed $latestClaim): ?Venue
    {
        if ($latestClaim instanceof VenueClaimRequest && $latestClaim->approved_venue_id !== null) {
            $approved = $venues->firstWhere('id', $latestClaim->approved_venue_id);

            if ($approved instanceof Venue) {
                return $approved;
            }
        }

        return $venues->first();
    }

    private function stage(
        User $user,
        ?VenueClaimInvitation $invitation,
        mixed $latestClaim,
        ?Venue $venue,
    ): string {
        if (! $user->hasVerifiedEmail()) {
            return 'verify_email';
        }

        if ($invitation !== null) {
            return 'confirm_invitation';
        }

        if ($latestClaim instanceof VenueClaimRequest) {
            if ($latestClaim->status === DirectoryClaimStatus::Pending) {
                return 'ownership_review';
            }

            if (in_array($latestClaim->status, [DirectoryClaimStatus::Rejected, DirectoryClaimStatus::Cancelled], true)
                && $venue === null) {
                return 'claim_attention';
            }
        }

        if ($venue?->application !== null) {
            if ($venue->application->status === VenueApplicationStatus::Pending) {
                return 'ownership_review';
            }

            if ($venue->application->status === VenueApplicationStatus::Rejected) {
                return 'application_attention';
            }
        }

        if ($venue === null) {
            return 'choose_venue';
        }

        if ((int) $venue->resources_count === 0) {
            return 'add_court';
        }

        $isPublic = $this->isPublic($venue);

        if ($venue->requiresPlatformReview()
            && $venue->verified_at === null
            && $venue->marketplace_review_requested_at !== null) {
            return 'marketplace_review';
        }

        if (! $isPublic) {
            return 'request_publication';
        }

        return 'complete';
    }

    /** @return array<int, array{key: string, label: string, description: string, state: string}> */
    private function steps(string $source, string $stage): array
    {
        $steps = $source === 'invited'
            ? [
                ['key' => 'account', 'label' => 'Verify your account', 'description' => 'Confirm the email address connected to your owner account.'],
                ['key' => 'venue', 'label' => 'Confirm the invited venue', 'description' => 'Review the pre-created public listing and confirm your relationship to it.'],
                ['key' => 'ownership', 'label' => 'Ownership review', 'description' => 'FinACourt independently checks the connection before owner tools unlock.'],
                ['key' => 'setup', 'label' => 'Set up courts and prices', 'description' => 'Add bookable courts, sports, opening hours, prices, and photos.'],
                ['key' => 'publish', 'label' => 'Request publication', 'description' => 'Ask FinACourt for the final marketplace check.'],
                ['key' => 'marketplace', 'label' => 'Final marketplace review', 'description' => 'FinACourt checks public details and booking readiness.'],
                ['key' => 'live', 'label' => 'Start receiving bookings', 'description' => 'Players can find and book the venue once it is live.'],
            ]
            : [
                ['key' => 'account', 'label' => 'Verify your account', 'description' => 'Confirm the email address connected to your owner account.'],
                ['key' => 'venue', 'label' => 'Find or add your venue', 'description' => 'Search the public guide first so you do not create a duplicate.'],
                ['key' => 'ownership', 'label' => 'Ownership review', 'description' => 'FinACourt independently checks the venue before private setup unlocks.'],
                ['key' => 'setup', 'label' => 'Set up courts and prices', 'description' => 'Add at least one active court players can book.'],
                ['key' => 'publish', 'label' => 'Request publication', 'description' => 'Review the setup and ask for the final marketplace check.'],
                ['key' => 'marketplace', 'label' => 'Final marketplace review', 'description' => 'FinACourt checks public details and booking readiness.'],
                ['key' => 'live', 'label' => 'Start receiving bookings', 'description' => 'Players can find and book the venue once it is live.'],
            ];

        $currentIndex = match ($stage) {
            'verify_email' => 0,
            'confirm_invitation', 'choose_venue' => 1,
            'ownership_review', 'claim_attention', 'application_attention' => 2,
            'add_court' => 3,
            'request_publication' => 4,
            'marketplace_review' => 5,
            'complete' => 6,
            default => 1,
        };

        return array_map(function (array $step, int $index) use ($currentIndex, $stage): array {
            $step['state'] = $index < $currentIndex || ($stage === 'complete' && $index === $currentIndex)
                ? 'complete'
                : ($index === $currentIndex ? 'current' : 'upcoming');

            return $step;
        }, $steps, array_keys($steps));
    }

    private function isPublic(Venue $venue): bool
    {
        return $venue->is_published
            && (int) $venue->active_resources_count > 0
            && (! $venue->requiresPlatformReview() || $venue->verified_at !== null);
    }

    /** @return array<string, mixed> */
    private function venueData(Venue $venue): array
    {
        return [
            'id' => $venue->getKey(),
            'name' => $venue->name,
            'slug' => $venue->slug,
            'city' => $venue->city,
            'province' => $venue->province,
            'is_published' => (bool) $venue->is_published,
            'is_verified' => $venue->verified_at !== null,
            'requires_platform_review' => $venue->requiresPlatformReview(),
            'marketplace_review_requested_at' => $venue->marketplace_review_requested_at?->format('M j, Y H:i'),
            'resources_count' => (int) $venue->resources_count,
            'active_resources_count' => (int) $venue->active_resources_count,
            'show_url' => route('owner.venues.show', $venue, false),
            'edit_url' => route('owner.venues.edit', ['venue' => $venue, 'onboarding' => 1], false),
            'add_court_url' => route('owner.venues.resources.create', ['venue' => $venue, 'onboarding' => 1], false),
            'public_url' => $this->isPublic($venue) ? route('marketplace.venues.show', $venue->slug, false) : null,
        ];
    }

    /** @return array<string, mixed> */
    private function applicationData(VenueApplication $application): array
    {
        return [
            'id' => $application->getKey(),
            'status' => $application->status->value,
            'status_label' => $application->status->label(),
            'submitted_at' => $application->submitted_at->format('M j, Y H:i'),
            'review_notes' => $application->review_notes,
        ];
    }

    /** @return array<string, mixed> */
    private function claimData(VenueClaimRequest $claim): array
    {
        return [
            'id' => $claim->getKey(),
            'status' => $claim->status->value,
            'status_label' => $claim->status->label(),
            'proof_status_label' => $claim->proof_status->label(),
            'review_notes' => $claim->review_notes,
            'created_at' => $claim->created_at->format('M j, Y H:i'),
            'listing' => $claim->listing?->only(['name', 'slug', 'address', 'city', 'province']),
        ];
    }

    /** @return array<string, mixed> */
    private function invitationData(VenueClaimInvitation $invitation, string $token): array
    {
        $listing = $invitation->listing;

        return [
            'token' => $token,
            'expires_at' => $invitation->expires_at->format('M j, Y H:i'),
            'submit_url' => route('owner.directory-claims.invitations.store', $token, false),
            'listing' => [
                ...$listing->only(['name', 'slug', 'address', 'city', 'province']),
                'sports' => $listing->sports->pluck('name')->values(),
            ],
        ];
    }
}
