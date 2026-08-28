<?php

namespace App\Http\Controllers\Platform;

use App\Enums\ReviewStatus;
use App\Http\Controllers\Controller;
use App\Models\VenueReview;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class VenueReviewController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', VenueReview::class);
        $validated = $request->validate([
            'status' => ['nullable', Rule::enum(ReviewStatus::class)],
        ]);
        $status = isset($validated['status']) ? ReviewStatus::from($validated['status']) : ReviewStatus::Pending;
        $paginator = VenueReview::query()
            ->where('status', $status)
            ->with([
                'venue:id,name,slug,city,province',
                'resource:id,name',
                'booking:id,reference,start_at,end_at,timezone',
                'player:id,name,email',
                'moderatedBy:id,name',
            ])
            ->latest()
            ->paginate(30)
            ->withQueryString();

        return Inertia::render('Platform/Reviews/Index', [
            'reviews' => $paginator->getCollection()->map(fn (VenueReview $review) => [
                'id' => $review->getKey(),
                'rating' => $review->rating,
                'body' => $review->body,
                'status' => $review->status->value,
                'status_label' => $review->status->label(),
                'venue' => $review->venue->only(['id', 'name', 'slug', 'city', 'province']),
                'resource' => $review->resource?->only(['id', 'name']),
                'booking' => [
                    'reference' => $review->booking->reference,
                    'ended_at' => $review->booking->end_at
                        ->setTimezone($review->booking->timezone)
                        ->format('M j, Y H:i'),
                ],
                'player' => [
                    'name' => $review->player->name,
                    'email' => $review->player->email,
                ],
                'moderated_by' => $review->moderatedBy?->name,
                'moderation_note' => $review->moderation_note,
                'created_at' => $review->created_at->format('M j, Y H:i'),
            ]),
            'filters' => ['status' => $status->value],
            'statusOptions' => collect(ReviewStatus::cases())->map(fn (ReviewStatus $option) => [
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

    public function update(Request $request, VenueReview $review): RedirectResponse
    {
        Gate::authorize('moderate', $review);
        $validated = $request->validate([
            'status' => ['required', Rule::in([
                ReviewStatus::Published->value,
                ReviewStatus::Rejected->value,
            ])],
            'moderation_note' => ['nullable', 'string', 'max:500'],
        ]);
        $status = ReviewStatus::from($validated['status']);

        $review->update([
            'status' => $status,
            'moderation_note' => $validated['moderation_note'] ?? null,
            'moderated_by_user_id' => $request->user()->getKey(),
            'moderated_at' => now(),
            'published_at' => $status === ReviewStatus::Published ? now() : null,
        ]);

        return back()->with('status', "Review marked {$status->label()}.");
    }
}
