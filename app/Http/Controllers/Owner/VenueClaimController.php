<?php

namespace App\Http\Controllers\Owner;

use App\Directory\OwnerClaimWorkspaceAccess;
use App\Directory\VenueClaimInvitationService;
use App\Directory\VenueClaimWorkflow;
use App\Enums\DirectoryClaimStatus;
use App\Enums\MembershipRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreVenueClaimRequest;
use App\Models\Membership;
use App\Models\VenueClaimRequest;
use App\Onboarding\OwnerVenueOnboarding;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class VenueClaimController extends Controller
{
    public function index(TenantContext $context): Response
    {
        $this->authorizeOwner($context);
        $claims = $context->organization()->venueClaimRequests()
            ->with(['listing:id,name,slug,city,province,status', 'approvedVenue:id,name,slug'])
            ->latest()
            ->get()
            ->map(fn (VenueClaimRequest $claim) => [
                'id' => $claim->getKey(),
                'status' => $claim->status->value,
                'status_label' => $claim->status->label(),
                'relationship' => str($claim->relationship_to_venue)->replace('_', ' ')->title()->toString(),
                'proof_status' => $claim->proof_status->value,
                'proof_status_label' => $claim->proof_status->label(),
                'proof_method' => $claim->proof_method?->label(),
                'proof_verified_at' => $claim->proof_verified_at?->format('M j, Y H:i'),
                'approval_available_at' => $claim->approval_available_at?->format('M j, Y H:i'),
                'listing' => $claim->listing->only(['name', 'slug', 'city', 'province']),
                'approved_venue' => $claim->approvedVenue?->only(['id', 'name', 'slug']),
                'review_notes' => $claim->review_notes,
                'created_at' => $claim->created_at->format('M j, Y H:i'),
            ]);

        return Inertia::render('Owner/DirectoryClaims/Index', ['claims' => $claims]);
    }

    public function create(
        string $invitationToken,
        TenantContext $context,
        VenueClaimInvitationService $invitations,
        OwnerClaimWorkspaceAccess $workspaceAccess,
        OwnerVenueOnboarding $onboarding,
    ): Response {
        $this->authorizeOwner($context);
        $invitation = $invitations->resolveUsable($invitationToken);
        $workspaceAccess->begin($context->organization());

        return Inertia::render('Owner/VenueOnboarding/Show', [
            'onboarding' => $onboarding->data(
                request()->user(),
                $context->organization(),
                $invitation,
                $invitationToken,
            ),
        ]);
    }

    public function store(
        StoreVenueClaimRequest $request,
        string $invitationToken,
        TenantContext $context,
        VenueClaimInvitationService $invitations,
        VenueClaimWorkflow $workflow,
        OwnerClaimWorkspaceAccess $workspaceAccess,
    ): RedirectResponse {
        $membership = $this->authorizeOwner($context);
        $invitations->resolveUsable($invitationToken);
        $workspaceAccess->begin($context->organization());
        $workflow->requestFromInvitation(
            $invitationToken,
            $request->user(),
            $context->organization(),
            $membership,
            $request->safe()->only([
                'relationship_to_venue',
                'verification_contact',
                'evidence_details',
            ]),
        );

        return redirect()->route('owner.onboarding.venue')
            ->with('status', 'Ownership request submitted. Your account email is already verified, so no additional code is required. FinACourt will now complete an independent venue check.');
    }

    public function cancel(
        VenueClaimRequest $claim,
        TenantContext $context,
        VenueClaimWorkflow $workflow,
    ): RedirectResponse {
        abort_unless($claim->status === DirectoryClaimStatus::Pending, 404);
        $workflow->cancel($claim, request()->user(), $context->organization());

        return back()->with('status', 'Your request was cancelled.');
    }

    private function authorizeOwner(TenantContext $context): Membership
    {
        $membership = $context->membership();
        abort_unless($membership?->role === MembershipRole::Owner, 403, 'Only the account owner can add a venue from the public guide.');

        return $membership;
    }
}
