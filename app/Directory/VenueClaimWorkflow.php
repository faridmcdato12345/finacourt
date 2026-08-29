<?php

namespace App\Directory;

use App\Enums\DirectoryClaimStatus;
use App\Enums\DirectoryListingStatus;
use App\Enums\MembershipRole;
use App\Models\AnalyticsEvent;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueClaimRequest;
use App\Models\VenueDirectoryListing;
use App\Support\VenueSlug;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class VenueClaimWorkflow
{
    public function __construct(
        private readonly VenueDirectoryAudit $audit,
        private readonly VenueSlug $venueSlug,
    ) {}

    /** @param array{relationship_to_venue: string, verification_contact: string, evidence_details: string} $data */
    public function request(
        VenueDirectoryListing $listing,
        User $requester,
        Organization $organization,
        Membership $membership,
        array $data,
    ): VenueClaimRequest {
        if ($membership->organization_id !== $organization->getKey()
            || $membership->user_id !== $requester->getKey()
            || $membership->role !== MembershipRole::Owner) {
            abort(403, 'Only the account owner can request to add this venue.');
        }

        return DB::transaction(function () use ($listing, $requester, $organization, $data): VenueClaimRequest {
            $locked = VenueDirectoryListing::query()->lockForUpdate()->findOrFail($listing->getKey());

            if (! $locked->isClaimable()) {
                throw ValidationException::withMessages([
                    'listing' => 'This venue is not available to add right now.',
                ]);
            }

            if ($locked->claimRequests()->where('status', DirectoryClaimStatus::Pending)->exists()) {
                throw ValidationException::withMessages([
                    'listing' => 'Someone has already asked us to add this venue, and we’re reviewing that request.',
                ]);
            }

            $claim = $locked->claimRequests()->create([
                'requester_user_id' => $requester->getKey(),
                'organization_id' => $organization->getKey(),
                'status' => DirectoryClaimStatus::Pending,
                'active_claim_key' => hash('sha256', "venue-directory-claim:{$locked->getKey()}"),
                ...$data,
            ]);
            $this->audit->record($locked, 'claim_requested', $requester, $claim, [
                'organization_id' => $organization->getKey(),
                'relationship' => $data['relationship_to_venue'],
            ]);

            return $claim;
        });
    }

    public function approve(VenueClaimRequest $claim, User $administrator, string $reviewNotes): Venue
    {
        return DB::transaction(function () use ($claim, $administrator, $reviewNotes): Venue {
            $lockedClaim = VenueClaimRequest::query()->lockForUpdate()->findOrFail($claim->getKey());
            $listing = VenueDirectoryListing::query()->lockForUpdate()->findOrFail(
                $lockedClaim->venue_directory_listing_id,
            );
            $this->guardPending($lockedClaim);

            if (! $lockedClaim->hasVerifiedOwnershipProof()) {
                throw ValidationException::withMessages([
                    'claim' => 'Confirm ownership through an independently sourced venue contact or recorded offline check before approving this request.',
                ]);
            }

            if (! $lockedClaim->isApprovalAvailable()) {
                throw ValidationException::withMessages([
                    'claim' => 'The safety hold has not finished yet. Review any disputes before approving this request.',
                ]);
            }

            if (! $listing->isClaimable()) {
                throw ValidationException::withMessages([
                    'claim' => 'This venue is no longer available to add.',
                ]);
            }

            $membership = Membership::query()
                ->where('organization_id', $lockedClaim->organization_id)
                ->where('user_id', $lockedClaim->requester_user_id)
                ->where('role', MembershipRole::Owner)
                ->first();

            if ($membership === null) {
                throw ValidationException::withMessages([
                    'claim' => 'The person who sent this request is no longer the owner of that account.',
                ]);
            }

            $venue = Venue::query()->create([
                'organization_id' => $lockedClaim->organization_id,
                'name' => $listing->name,
                'slug' => $this->venueSlug->generate($listing->name),
                'description' => $listing->description,
                'address' => $listing->address,
                'city' => $listing->city,
                'city_slug' => $listing->city_slug,
                'province' => $listing->province,
                'province_slug' => $listing->province_slug,
                'psgc_region_code' => $listing->psgc_region_code,
                'psgc_province_code' => $listing->psgc_province_code,
                'psgc_city_municipality_code' => $listing->psgc_city_municipality_code,
                'latitude' => $listing->coordinates_verified_at ? $listing->latitude : null,
                'longitude' => $listing->coordinates_verified_at ? $listing->longitude : null,
                'coordinates_source' => $listing->coordinates_verified_at ? 'directory_listing' : null,
                'coordinates_verified_at' => $listing->coordinates_verified_at,
                'phone' => $listing->phone,
                'email' => $listing->email,
                'website' => $listing->website,
                'is_published' => false,
                'claimed_at' => now('UTC'),
                // Claim approval proves control, not marketplace quality.
                'verified_at' => null,
            ]);
            $venue->sports()->sync($listing->sports()->pluck('sports.id'));

            foreach ($listing->hours()->get() as $hour) {
                $venue->operatingHours()->create([
                    'day_of_week' => $hour->day_of_week,
                    'is_closed' => $hour->is_closed,
                    'opens_at' => $hour->opens_at,
                    'closes_at' => $hour->closes_at,
                ]);
            }

            $lockedClaim->update([
                'status' => DirectoryClaimStatus::Approved,
                'active_claim_key' => null,
                'approved_venue_id' => $venue->getKey(),
                'reviewed_by_user_id' => $administrator->getKey(),
                'review_notes' => $reviewNotes,
                'reviewed_at' => now('UTC'),
            ]);
            $listing->update([
                'status' => DirectoryListingStatus::Claimed,
                'claimed_venue_id' => $venue->getKey(),
                'claimed_at' => now('UTC'),
            ]);

            // The approved owner may see legitimate pre-claim profile views.
            // No visitor identity or raw browsing history is exposed; the
            // existing daily-HMAC event remains the only stored evidence.
            $transferredEvents = AnalyticsEvent::query()
                ->where('venue_directory_listing_id', $listing->getKey())
                ->whereNull('organization_id')
                ->whereNull('venue_id')
                ->update([
                    'organization_id' => $venue->organization_id,
                    'venue_id' => $venue->getKey(),
                ]);

            $this->audit->record($listing, 'claim_approved', $administrator, $lockedClaim, [
                'organization_id' => $venue->organization_id,
                'venue_id' => $venue->getKey(),
                'preclaim_profile_events_transferred' => $transferredEvents,
            ]);

            return $venue;
        });
    }

    public function reject(VenueClaimRequest $claim, User $administrator, string $reviewNotes): void
    {
        DB::transaction(function () use ($claim, $administrator, $reviewNotes): void {
            $lockedClaim = VenueClaimRequest::query()->lockForUpdate()->findOrFail($claim->getKey());
            $listing = VenueDirectoryListing::query()->lockForUpdate()->findOrFail(
                $lockedClaim->venue_directory_listing_id,
            );
            $this->guardPending($lockedClaim);
            $lockedClaim->update([
                'status' => DirectoryClaimStatus::Rejected,
                'active_claim_key' => null,
                'reviewed_by_user_id' => $administrator->getKey(),
                'review_notes' => $reviewNotes,
                'reviewed_at' => now('UTC'),
            ]);
            $this->audit->record($listing, 'claim_rejected', $administrator, $lockedClaim, [
                'reason' => Str::limit($reviewNotes, 500),
            ]);
        });
    }

    public function verifyClaimedVenueForMarketplace(
        VenueDirectoryListing $listing,
        User $administrator,
        string $notes,
    ): Venue {
        abort_unless($administrator->is_platform_admin, 403);

        return DB::transaction(function () use ($listing, $administrator, $notes): Venue {
            $locked = VenueDirectoryListing::query()->lockForUpdate()->findOrFail($listing->getKey());

            if ($locked->status !== DirectoryListingStatus::Claimed || $locked->claimed_venue_id === null) {
                throw ValidationException::withMessages([
                    'listing' => 'This directory record has not completed an approved ownership request.',
                ]);
            }

            $approvedClaim = $locked->claimRequests()
                ->where('status', DirectoryClaimStatus::Approved)
                ->where('approved_venue_id', $locked->claimed_venue_id)
                ->latest('id')
                ->first();

            if ($approvedClaim === null || ! $approvedClaim->hasVerifiedOwnershipProof()) {
                throw ValidationException::withMessages([
                    'listing' => 'Verified ownership proof is missing for this claimed venue.',
                ]);
            }

            $venue = Venue::query()->lockForUpdate()->findOrFail($locked->claimed_venue_id);

            if (! $venue->is_published || ! $venue->resources()->marketplace()->exists()) {
                throw ValidationException::withMessages([
                    'listing' => 'Finish the venue details, add a court players can book, and choose “Show this venue to players” before asking FinACourt to check it.',
                ]);
            }

            $venue->update(['verified_at' => now('UTC')]);
            $this->audit->record($locked, 'claimed_venue_marketplace_verified', $administrator, $approvedClaim, [
                'venue_id' => $venue->getKey(),
                'verification_notes' => Str::limit($notes, 2000),
            ]);

            return $venue;
        });
    }

    public function revokeClaimedVenueMarketplaceAccess(
        VenueDirectoryListing $listing,
        User $administrator,
        string $reason,
    ): void {
        abort_unless($administrator->is_platform_admin, 403);

        DB::transaction(function () use ($listing, $administrator, $reason): void {
            $locked = VenueDirectoryListing::query()->lockForUpdate()->findOrFail($listing->getKey());

            if ($locked->status !== DirectoryListingStatus::Claimed || $locked->claimed_venue_id === null) {
                throw ValidationException::withMessages(['listing' => 'This directory record has no claimed venue.']);
            }

            $venue = Venue::query()->lockForUpdate()->findOrFail($locked->claimed_venue_id);
            $venue->update(['verified_at' => null, 'is_published' => false]);
            $this->audit->record($locked, 'claimed_venue_marketplace_access_revoked', $administrator, changes: [
                'venue_id' => $venue->getKey(),
                'reason' => Str::limit($reason, 1000),
            ]);
        });
    }

    public function cancel(VenueClaimRequest $claim, User $requester, Organization $organization): void
    {
        DB::transaction(function () use ($claim, $requester, $organization): void {
            $lockedClaim = VenueClaimRequest::query()->lockForUpdate()->findOrFail($claim->getKey());

            if ($lockedClaim->requester_user_id !== $requester->getKey()
                || $lockedClaim->organization_id !== $organization->getKey()) {
                abort(404);
            }

            $this->guardPending($lockedClaim);
            $lockedClaim->update([
                'status' => DirectoryClaimStatus::Cancelled,
                'active_claim_key' => null,
                'reviewed_at' => now('UTC'),
            ]);
            $this->audit->record($lockedClaim->listing, 'claim_cancelled', $requester, $lockedClaim);
        });
    }

    private function guardPending(VenueClaimRequest $claim): void
    {
        if ($claim->status !== DirectoryClaimStatus::Pending) {
            throw ValidationException::withMessages([
                'claim' => 'This request has already been reviewed.',
            ]);
        }
    }
}
