<?php

namespace App\Http\Controllers\Owner;

use App\Directory\VenueClaimProofService;
use App\Directory\VenueClaimWorkflow;
use App\Enums\DirectoryClaimStatus;
use App\Enums\MembershipRole;
use App\Enums\VenueClaimProofStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreVenueClaimRequest;
use App\Models\Membership;
use App\Models\VenueClaimRequest;
use App\Models\VenueDirectoryListing;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class VenueClaimController extends Controller
{
    public function index(TenantContext $context): Response
    {
        $this->authorizeOwner($context);
        $claims = $context->organization()->venueClaimRequests()
            ->with(['listing:id,name,slug,city,province,status,email', 'approvedVenue:id,name,slug'])
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
                'proof_destination' => $claim->proof_destination,
                'proof_sent_at' => $claim->proof_sent_at?->format('M j, Y H:i'),
                'proof_verified_at' => $claim->proof_verified_at?->format('M j, Y H:i'),
                'approval_available_at' => $claim->approval_available_at?->format('M j, Y H:i'),
                'can_request_email_code' => $claim->proof_status !== VenueClaimProofStatus::Locked
                    && filter_var($claim->listing->email, FILTER_VALIDATE_EMAIL) !== false,
                'listing' => $claim->listing->only(['name', 'slug', 'city', 'province']),
                'approved_venue' => $claim->approvedVenue?->only(['id', 'name', 'slug']),
                'review_notes' => $claim->review_notes,
                'created_at' => $claim->created_at->format('M j, Y H:i'),
            ]);

        return Inertia::render('Owner/DirectoryClaims/Index', ['claims' => $claims]);
    }

    public function create(VenueDirectoryListing $directoryListing, TenantContext $context): Response
    {
        $this->authorizeOwner($context);
        abort_unless($directoryListing->isClaimable(), 404);
        $directoryListing->load('sports:id,name');

        return Inertia::render('Owner/DirectoryClaims/Create', [
            'listing' => [
                ...$directoryListing->only(['name', 'slug', 'address', 'city', 'province']),
                'sports' => $directoryListing->sports->pluck('name'),
            ],
            'organization' => $context->organization()->only(['id', 'name']),
        ]);
    }

    public function store(
        StoreVenueClaimRequest $request,
        VenueDirectoryListing $directoryListing,
        TenantContext $context,
        VenueClaimWorkflow $workflow,
        VenueClaimProofService $proofs,
    ): RedirectResponse {
        $membership = $this->authorizeOwner($context);
        $claim = $workflow->request(
            $directoryListing,
            $request->user(),
            $context->organization(),
            $membership,
            $request->validated(),
        );

        $status = 'Request received. FinACourt must confirm ownership before anything is added to your account.';

        if (filter_var($directoryListing->email, FILTER_VALIDATE_EMAIL) !== false) {
            try {
                $proofs->issuePublicEmailCode($claim, $request->user(), $context->organization());
                $status = 'Request received. We sent a verification code to the venue email already shown in the public guide.';
            } catch (\Throwable $exception) {
                Log::warning('Venue claim email challenge delivery failed.', [
                    'claim_id' => $claim->getKey(),
                    'exception' => $exception::class,
                ]);
                $status = 'Request received, but the venue email could not be reached. FinACourt must complete an independent manual check.';
            }
        }

        return redirect()->route('owner.directory-claims.index')
            ->with('status', $status);
    }

    public function resendEmailCode(
        VenueClaimRequest $claim,
        TenantContext $context,
        VenueClaimProofService $proofs,
    ): RedirectResponse {
        $this->authorizeOwner($context);
        $proofs->issuePublicEmailCode($claim, request()->user(), $context->organization());

        return back()->with('status', 'A new code was sent to the venue’s public email. Earlier codes no longer work.');
    }

    public function verifyEmailCode(
        Request $request,
        VenueClaimRequest $claim,
        TenantContext $context,
        VenueClaimProofService $proofs,
    ): RedirectResponse {
        $this->authorizeOwner($context);
        $validated = $request->validate([
            'code' => ['required', 'string', 'regex:/^\d{6}$/'],
        ]);
        $proofs->verifyPublicEmailCode(
            $claim,
            $request->user(),
            $context->organization(),
            $validated['code'],
        );

        return back()->with('status', 'Ownership proof confirmed. A safety hold now gives the venue time to report an unauthorized request before approval.');
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
