<?php

namespace App\Directory;

use App\Enums\MembershipRole;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueClaimRequest;
use App\Notifications\OwnerVenueClaimApprovedNotification;
use App\Notifications\OwnerVenuePublishedNotification;
use App\Notifications\PlatformClaimedVenueReviewRequestedNotification;
use App\Notifications\PlatformVenueClaimSubmittedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class VenueClaimNotifier
{
    public function submittedForMarketplaceReview(Venue $venue, User $requester): void
    {
        $venueId = $venue->getKey();
        $requesterId = $requester->getKey();

        DB::afterCommit(function () use ($venueId, $requesterId): void {
            $fresh = Venue::query()
                ->with('organization:id,name')
                ->find($venueId);
            $requestedBy = User::query()->find($requesterId);
            $listing = $fresh?->claimedDirectoryListings()->first(['id', 'slug']);
            $application = $fresh?->application()->first(['id']);

            if (! $fresh || ! $fresh->organization || ! $requestedBy || (! $listing && ! $application)) {
                return;
            }

            $administrators = User::query()
                ->where('is_platform_admin', true)
                ->whereNotNull('email')
                ->get();

            if ($administrators->isEmpty()) {
                Log::warning('Claimed venue final review notification has no platform administrator recipient.', [
                    'venue_id' => $venueId,
                ]);

                return;
            }

            try {
                Notification::send($administrators, new PlatformClaimedVenueReviewRequestedNotification(
                    venueId: $fresh->getKey(),
                    venueName: $fresh->name,
                    venueLocation: collect([$fresh->city, $fresh->province])->filter()->join(', '),
                    organizationName: $fresh->organization->name,
                    requesterName: $requestedBy->name,
                    requesterEmail: $requestedBy->email,
                    url: $listing
                        ? route('platform.directory.edit', $listing)
                        : route('platform.venue-applications.index'),
                ));
            } catch (\Throwable $exception) {
                Log::error('Claimed venue final review notification could not be queued.', [
                    'venue_id' => $venueId,
                    'exception' => $exception::class,
                ]);
            }
        });
    }

    public function marketplaceApproved(Venue $venue): void
    {
        $venueId = $venue->getKey();

        DB::afterCommit(function () use ($venueId): void {
            $fresh = Venue::query()
                ->with('organization:id,name')
                ->find($venueId);

            if (! $fresh || ! $fresh->organization) {
                return;
            }

            $owners = User::query()
                ->whereNotNull('email')
                ->whereHas('memberships', fn ($query) => $query
                    ->where('organization_id', $fresh->organization_id)
                    ->where('role', MembershipRole::Owner))
                ->get();

            if ($owners->isEmpty()) {
                Log::warning('Published venue notification has no court owner recipient.', [
                    'venue_id' => $venueId,
                ]);

                return;
            }

            try {
                Notification::send($owners, new OwnerVenuePublishedNotification(
                    venueId: $fresh->getKey(),
                    venueName: $fresh->name,
                    organizationName: $fresh->organization->name,
                    url: route('marketplace.venues.show', $fresh->slug),
                ));
            } catch (\Throwable $exception) {
                Log::error('Published venue notification could not be queued.', [
                    'venue_id' => $venueId,
                    'exception' => $exception::class,
                ]);
            }
        });
    }

    public function submittedForIndependentReview(VenueClaimRequest $claim): void
    {
        $claimId = $claim->getKey();

        DB::afterCommit(function () use ($claimId): void {
            $fresh = VenueClaimRequest::query()
                ->with(['listing:id,name,city,province', 'requester:id,name,email', 'organization:id,name'])
                ->find($claimId);

            if (! $fresh || ! $fresh->listing || ! $fresh->requester || ! $fresh->organization) {
                return;
            }

            $administrators = User::query()
                ->where('is_platform_admin', true)
                ->whereNotNull('email')
                ->get();

            if ($administrators->isEmpty()) {
                Log::warning('Venue claim review notification has no platform administrator recipient.', [
                    'claim_id' => $claimId,
                ]);

                return;
            }

            try {
                Notification::send($administrators, new PlatformVenueClaimSubmittedNotification(
                    claimId: $fresh->getKey(),
                    venueName: $fresh->listing->name,
                    venueLocation: collect([$fresh->listing->city, $fresh->listing->province])
                        ->filter()
                        ->join(', '),
                    organizationName: $fresh->organization->name,
                    requesterName: $fresh->requester->name,
                    requesterEmail: $fresh->requester->email,
                    relationship: str($fresh->relationship_to_venue)->replace('_', ' ')->title()->toString(),
                    url: route('platform.directory.index'),
                ));
            } catch (\Throwable $exception) {
                Log::error('Venue claim review notification could not be queued.', [
                    'claim_id' => $claimId,
                    'exception' => $exception::class,
                ]);
            }
        });
    }

    public function approved(VenueClaimRequest $claim, Venue $venue): void
    {
        $claimId = $claim->getKey();
        $venueId = $venue->getKey();

        DB::afterCommit(function () use ($claimId, $venueId): void {
            $fresh = VenueClaimRequest::query()
                ->with(['requester:id,name,email', 'organization:id,name'])
                ->find($claimId);
            $approvedVenue = Venue::query()->find($venueId);

            if (! $fresh || ! $fresh->requester || ! $fresh->organization || ! $approvedVenue) {
                return;
            }

            try {
                $fresh->requester->notify(new OwnerVenueClaimApprovedNotification(
                    claimId: $fresh->getKey(),
                    venueName: $approvedVenue->name,
                    organizationName: $fresh->organization->name,
                    url: route('owner.venues.show', $approvedVenue),
                ));
            } catch (\Throwable $exception) {
                Log::error('Venue claim approval notification could not be queued.', [
                    'claim_id' => $claimId,
                    'venue_id' => $venueId,
                    'exception' => $exception::class,
                ]);
            }
        });
    }
}
