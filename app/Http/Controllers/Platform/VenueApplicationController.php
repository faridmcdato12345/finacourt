<?php

namespace App\Http\Controllers\Platform;

use App\Enums\VenueApplicationStatus;
use App\Http\Controllers\Controller;
use App\Models\VenueApplication;
use App\Onboarding\VenueApplicationWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VenueApplicationController extends Controller
{
    public function index(): Response
    {
        $applications = VenueApplication::query()
            ->with([
                'venue' => fn ($query) => $query
                    ->withCount(['resources as active_resources_count' => fn ($query) => $query->marketplace()]),
                'organization:id,name',
                'submittedBy:id,name,email',
                'reviewedBy:id,name',
            ])
            ->latest('submitted_at')
            ->get();

        return Inertia::render('Platform/VenueApplications/Index', [
            'ownershipReviews' => $applications
                ->whereIn('status', [VenueApplicationStatus::Pending, VenueApplicationStatus::Rejected])
                ->map(fn (VenueApplication $application) => $this->payload($application))
                ->values(),
            'marketplaceReviews' => $applications
                ->where('status', VenueApplicationStatus::Approved)
                ->filter(fn (VenueApplication $application) => $application->venue->verified_at === null)
                ->map(fn (VenueApplication $application) => $this->payload($application))
                ->values(),
            'completedApplications' => $applications
                ->where('status', VenueApplicationStatus::Approved)
                ->filter(fn (VenueApplication $application) => $application->venue->verified_at !== null)
                ->take(20)
                ->map(fn (VenueApplication $application) => $this->payload($application))
                ->values(),
        ]);
    }

    public function approve(
        Request $request,
        VenueApplication $application,
        VenueApplicationWorkflow $workflow,
    ): RedirectResponse {
        $validated = $request->validate([
            'review_notes' => ['required', 'string', 'min:20', 'max:2000'],
        ]);
        $venue = $workflow->approve($application, $request->user(), $validated['review_notes']);

        return back()->with('status', "Ownership approved. {$venue->name} is private and ready for the owner to finish setup.");
    }

    public function reject(
        Request $request,
        VenueApplication $application,
        VenueApplicationWorkflow $workflow,
    ): RedirectResponse {
        $validated = $request->validate([
            'review_notes' => ['required', 'string', 'min:20', 'max:2000'],
        ]);
        $workflow->reject($application, $request->user(), $validated['review_notes']);

        return back()->with('status', 'The owner was emailed with the changes needed before resubmitting.');
    }

    public function verifyMarketplace(
        Request $request,
        VenueApplication $application,
        VenueApplicationWorkflow $workflow,
    ): RedirectResponse {
        $validated = $request->validate([
            'review_notes' => ['required', 'string', 'min:20', 'max:2000'],
        ]);
        $venue = $workflow->verifyForMarketplace($application, $request->user(), $validated['review_notes']);

        return back()->with('status', "Final review completed. {$venue->name} can now appear to players while published.");
    }

    /** @return array<string, mixed> */
    private function payload(VenueApplication $application): array
    {
        return [
            'id' => $application->getKey(),
            'status' => $application->status->value,
            'status_label' => $application->status->label(),
            'submitted_at' => $application->submitted_at->format('M j, Y H:i'),
            'reviewed_at' => $application->reviewed_at?->format('M j, Y H:i'),
            'review_notes' => $application->review_notes,
            'organization' => $application->organization->only(['id', 'name']),
            'requester' => $application->submittedBy->only(['name', 'email']),
            'reviewer' => $application->reviewedBy?->only(['name']),
            'venue' => [
                ...$application->venue->only([
                    'id', 'name', 'slug', 'address', 'city', 'province', 'phone', 'email', 'website',
                    'is_published',
                ]),
                'active_resources_count' => (int) $application->venue->active_resources_count,
                'marketplace_review_requested_at' => $application->venue->marketplace_review_requested_at?->format('M j, Y H:i'),
                'verified_at' => $application->venue->verified_at?->format('M j, Y H:i'),
            ],
        ];
    }
}
