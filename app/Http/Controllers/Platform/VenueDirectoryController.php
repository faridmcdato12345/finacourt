<?php

namespace App\Http\Controllers\Platform;

use App\Directory\VenueClaimProofService;
use App\Directory\VenueClaimWorkflow;
use App\Directory\VenueDirectoryAudit;
use App\Directory\VenueDirectoryManager;
use App\Enums\DirectoryClaimStatus;
use App\Enums\DirectoryListingStatus;
use App\Enums\DirectoryReportStatus;
use App\Enums\DirectorySourceType;
use App\Enums\VenueClaimProofMethod;
use App\Enums\Weekday;
use App\Http\Controllers\Controller;
use App\Http\Requests\SaveVenueDirectoryListingRequest;
use App\Models\PsgcLocation;
use App\Models\Sport;
use App\Models\VenueClaimRequest;
use App\Models\VenueDirectoryListing;
use App\Models\VenueDirectoryReport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class VenueDirectoryController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::enum(DirectoryListingStatus::class)],
        ]);
        $status = isset($validated['status'])
            ? DirectoryListingStatus::from($validated['status'])
            : null;
        $paginator = VenueDirectoryListing::query()
            ->when($status, fn ($query) => $query->where('status', $status))
            ->with(['sports:id,name', 'createdBy:id,name', 'verifiedBy:id,name', 'claimedVenue:id,name,slug'])
            ->withCount(['claimRequests as pending_claims_count' => fn ($query) => $query->where('status', DirectoryClaimStatus::Pending)])
            ->latest()
            ->paginate(30)
            ->withQueryString();

        return Inertia::render('Platform/Directory/Index', [
            'listings' => $paginator->getCollection()->map(fn (VenueDirectoryListing $listing) => $this->listingPayload($listing)),
            'claims' => VenueClaimRequest::query()
                ->where('status', DirectoryClaimStatus::Pending)
                ->with(['listing:id,name,slug,city,province,status,email', 'requester:id,name,email', 'organization:id,name'])
                ->oldest()
                ->get()
                ->map(fn (VenueClaimRequest $claim) => [
                    'id' => $claim->getKey(),
                    'listing' => $claim->listing->only(['name', 'slug', 'city', 'province']),
                    'requester' => $claim->requester->only(['name', 'email']),
                    'organization' => $claim->organization->only(['id', 'name']),
                    'relationship' => str($claim->relationship_to_venue)->replace('_', ' ')->title()->toString(),
                    'proof_status' => $claim->proof_status->value,
                    'proof_status_label' => $claim->proof_status->label(),
                    'proof_method' => $claim->proof_method?->label(),
                    'proof_destination' => $claim->proof_destination,
                    'proof_verified_at' => $claim->proof_verified_at?->format('M j, Y H:i'),
                    'approval_available_at' => $claim->approval_available_at?->format('M j, Y H:i'),
                    'can_approve' => $claim->isApprovalAvailable(),
                    'verification_contact' => $claim->verification_contact,
                    'evidence_details' => $claim->evidence_details,
                    'created_at' => $claim->created_at->format('M j, Y H:i'),
                ]),
            'reports' => VenueDirectoryReport::query()
                ->where('status', DirectoryReportStatus::Pending)
                ->with('listing:id,name,slug')
                ->oldest()
                ->get()
                ->map(fn (VenueDirectoryReport $report) => [
                    'id' => $report->getKey(),
                    'listing' => $report->listing->only(['name', 'slug']),
                    'report_type' => str($report->report_type)->title()->toString(),
                    'contact_email' => $report->contact_email,
                    'details' => $report->details,
                    'created_at' => $report->created_at->format('M j, Y H:i'),
                ]),
            'filters' => ['status' => $status?->value],
            'claimProofMethods' => collect(VenueClaimProofMethod::cases())
                ->filter(fn (VenueClaimProofMethod $method) => $method->isManualReview())
                ->map(fn (VenueClaimProofMethod $method) => [
                    'value' => $method->value,
                    'label' => $method->label(),
                ])
                ->values(),
            'statusOptions' => collect(DirectoryListingStatus::cases())->map(fn ($option) => [
                'value' => $option->value,
                'label' => $option->label(),
            ]),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'previous' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Platform/Directory/Create', $this->formOptions());
    }

    public function store(
        SaveVenueDirectoryListingRequest $request,
        VenueDirectoryManager $manager,
    ): RedirectResponse {
        $listing = $manager->create($request->validated(), $request->user());

        return redirect()->route('platform.directory.edit', $listing)
            ->with('status', 'Venue saved as a draft. Check the source details before showing it publicly.');
    }

    public function edit(VenueDirectoryListing $directoryListing): Response
    {
        $directoryListing->load([
            'sports:id',
            'hours',
            'audits.actor:id,name',
            'claimedVenue' => fn ($query) => $query
                ->with('organization:id,name')
                ->withCount(['resources as active_resources_count' => fn ($query) => $query->marketplace()]),
        ]);

        return Inertia::render('Platform/Directory/Edit', [
            ...$this->formOptions(),
            'listing' => [
                ...$directoryListing->only([
                    'name', 'description', 'address', 'city', 'province', 'country',
                    'psgc_region_code', 'psgc_province_code', 'psgc_city_municipality_code',
                    'latitude', 'longitude', 'phone', 'email', 'website', 'source_url',
                    'source_reference', 'verification_notes',
                ]),
                'slug' => $directoryListing->slug,
                'status' => $directoryListing->status->value,
                'status_label' => $directoryListing->status->label(),
                'source_type' => $directoryListing->source_type->value,
                'psgc_parent_code' => $directoryListing->psgc_province_code
                    ?: $directoryListing->psgc_region_code,
                'coordinates_verified' => $directoryListing->coordinates_verified_at !== null,
                'sports' => $directoryListing->sports->modelKeys(),
                'hours' => $directoryListing->hours->map(fn ($hour) => [
                    'day_of_week' => $hour->day_of_week->value,
                    'is_closed' => $hour->is_closed,
                    'opens_at' => $hour->opens_at ? substr($hour->opens_at, 0, 5) : null,
                    'closes_at' => $hour->closes_at ? substr($hour->closes_at, 0, 5) : null,
                ]),
                'last_verified_at' => $directoryListing->last_verified_at?->format('M j, Y H:i'),
                'claimed_venue' => $directoryListing->claimedVenue ? [
                    'id' => $directoryListing->claimedVenue->getKey(),
                    'name' => $directoryListing->claimedVenue->name,
                    'organization' => $directoryListing->claimedVenue->organization?->name,
                    'is_published' => $directoryListing->claimedVenue->is_published,
                    'is_marketplace_verified' => $directoryListing->claimedVenue->verified_at !== null,
                    'verified_at' => $directoryListing->claimedVenue->verified_at?->format('M j, Y H:i'),
                    'active_resources_count' => $directoryListing->claimedVenue->active_resources_count,
                ] : null,
                'audits' => $directoryListing->audits->take(30)->map(fn ($audit) => [
                    'event_type' => str($audit->event_type)->replace('_', ' ')->title()->toString(),
                    'actor' => $audit->actor?->name ?? 'System',
                    'changes' => $audit->changes,
                    'occurred_at' => $audit->occurred_at->format('M j, Y H:i'),
                ]),
            ],
        ]);
    }

    public function update(
        SaveVenueDirectoryListingRequest $request,
        VenueDirectoryListing $directoryListing,
        VenueDirectoryManager $manager,
    ): RedirectResponse {
        $manager->update($directoryListing, $request->validated(), $request->user());

        return back()->with('status', 'Changes saved. Check the venue details again before showing the page publicly.');
    }

    public function verify(Request $request, VenueDirectoryListing $directoryListing, VenueDirectoryManager $manager): RedirectResponse
    {
        $validated = $request->validate(['verification_notes' => ['required', 'string', 'min:10', 'max:2000']]);
        $manager->verify($directoryListing, $request->user(), $validated['verification_notes']);

        return back()->with('status', 'Venue details marked as checked. You can now show this page publicly.');
    }

    public function publish(Request $request, VenueDirectoryListing $directoryListing, VenueDirectoryManager $manager): RedirectResponse
    {
        $manager->publish($directoryListing, $request->user());

        return back()->with('status', 'This venue is now visible in the public guide.');
    }

    public function close(Request $request, VenueDirectoryListing $directoryListing, VenueDirectoryManager $manager): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:500']]);
        $manager->markClosed($directoryListing, $request->user(), $validated['reason']);

        return back()->with('status', 'This venue is marked closed and no longer appears in guide results.');
    }

    public function remove(Request $request, VenueDirectoryListing $directoryListing, VenueDirectoryManager $manager): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:500']]);
        $manager->remove($directoryListing, $request->user(), $validated['reason']);

        return redirect()->route('platform.directory.index')->with('status', 'The venue was hidden from the public guide.');
    }

    public function approveClaim(Request $request, VenueClaimRequest $claim, VenueClaimWorkflow $workflow): RedirectResponse
    {
        $validated = $request->validate(['review_notes' => ['required', 'string', 'min:10', 'max:2000']]);
        $venue = $workflow->approve($claim, $request->user(), $validated['review_notes']);

        return back()->with('status', "Request approved. {$venue->name} is now a private venue in the owner’s account, ready for setup.");
    }

    public function verifyClaimProof(
        Request $request,
        VenueClaimRequest $claim,
        VenueClaimProofService $proofs,
    ): RedirectResponse {
        $validated = $request->validate([
            'proof_method' => ['required', Rule::enum(VenueClaimProofMethod::class)],
            'proof_notes' => ['required', 'string', 'min:20', 'max:3000'],
        ]);
        $proofs->recordManualVerification(
            $claim,
            $request->user(),
            VenueClaimProofMethod::from($validated['proof_method']),
            $validated['proof_notes'],
        );

        return back()->with('status', 'Independent ownership proof recorded. Approval remains locked during the safety hold.');
    }

    public function verifyClaimedVenue(
        Request $request,
        VenueDirectoryListing $directoryListing,
        VenueClaimWorkflow $workflow,
    ): RedirectResponse {
        $validated = $request->validate([
            'verification_notes' => ['required', 'string', 'min:20', 'max:2000'],
        ]);
        $workflow->verifyClaimedVenueForMarketplace(
            $directoryListing,
            $request->user(),
            $validated['verification_notes'],
        );

        return back()->with('status', 'Marketplace review completed. The venue can appear publicly while its owner keeps it published.');
    }

    public function revokeClaimedVenue(
        Request $request,
        VenueDirectoryListing $directoryListing,
        VenueClaimWorkflow $workflow,
    ): RedirectResponse {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:20', 'max:1000'],
        ]);
        $workflow->revokeClaimedVenueMarketplaceAccess(
            $directoryListing,
            $request->user(),
            $validated['reason'],
        );

        return back()->with('status', 'Marketplace access removed immediately. The venue was also unpublished.');
    }

    public function rejectClaim(Request $request, VenueClaimRequest $claim, VenueClaimWorkflow $workflow): RedirectResponse
    {
        $validated = $request->validate(['review_notes' => ['required', 'string', 'min:10', 'max:2000']]);
        $workflow->reject($claim, $request->user(), $validated['review_notes']);

        return back()->with('status', 'The request could not be confirmed. Another owner can request this venue later.');
    }

    public function reviewReport(
        Request $request,
        VenueDirectoryReport $report,
        VenueDirectoryAudit $audit,
    ): RedirectResponse {
        $validated = $request->validate([
            'status' => ['required', Rule::in([DirectoryReportStatus::Resolved->value, DirectoryReportStatus::Dismissed->value])],
            'review_notes' => ['required', 'string', 'min:10', 'max:1000'],
        ]);
        abort_unless($report->status === DirectoryReportStatus::Pending, 422);
        $report->update([
            'status' => DirectoryReportStatus::from($validated['status']),
            'reviewed_by_user_id' => $request->user()->getKey(),
            'review_notes' => $validated['review_notes'],
            'reviewed_at' => now('UTC'),
        ]);
        $audit->record($report->listing, 'public_report_reviewed', $request->user(), changes: [
            'report_id' => $report->getKey(),
            'status' => $validated['status'],
        ]);

        return back()->with('status', 'Thanks — the suggested correction has been reviewed.');
    }

    /** @return array<string, mixed> */
    private function formOptions(): array
    {
        return [
            'sports' => Sport::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'locationParents' => PsgcLocation::query()
                ->whereIn('level', ['province', 'region', 'area'])
                ->whereHas('children', fn ($query) => $query->whereIn('level', ['city', 'municipality']))
                ->orderBy('name')
                ->get(['code', 'name', 'level'])
                ->map(fn (PsgcLocation $location) => [
                    'code' => $location->code,
                    'name' => $location->name,
                    'level' => $location->level,
                    'label' => $location->name.' — '.ucfirst($location->level),
                ]),
            'sourceTypes' => collect(DirectorySourceType::cases())->map(fn ($type) => [
                'value' => $type->value,
                'label' => $type->label(),
            ]),
            'weekdays' => collect(Weekday::cases())->map(fn ($day) => [
                'value' => $day->value,
                'label' => $day->label(),
            ]),
        ];
    }

    /** @return array<string, mixed> */
    private function listingPayload(VenueDirectoryListing $listing): array
    {
        return [
            'slug' => $listing->slug,
            'name' => $listing->name,
            'city' => $listing->city,
            'province' => $listing->province,
            'status' => $listing->status->value,
            'status_label' => $listing->status->label(),
            'source_type' => $listing->source_type->label(),
            'source_url' => $listing->source_url,
            'last_verified_at' => $listing->last_verified_at?->format('M j, Y'),
            'sports' => $listing->sports->pluck('name'),
            'created_by' => $listing->createdBy?->name,
            'verified_by' => $listing->verifiedBy?->name,
            'claimed_venue' => $listing->claimedVenue?->only(['id', 'name', 'slug']),
            'pending_claims_count' => $listing->pending_claims_count,
        ];
    }
}
