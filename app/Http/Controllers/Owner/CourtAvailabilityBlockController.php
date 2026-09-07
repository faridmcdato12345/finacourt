<?php

namespace App\Http\Controllers\Owner;

use App\Bookings\CreateCourtAvailabilityBlock;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCourtAvailabilityBlockRequest;
use App\Models\Booking;
use App\Models\CourtAvailabilityBlock;
use App\Models\CourtResource;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CourtAvailabilityBlockController extends Controller
{
    public function create(TenantContext $context): Response
    {
        $organization = $context->organization();
        Gate::authorize('create', [Booking::class, $organization]);

        $resources = CourtResource::query()
            ->whereHas('venue', fn ($query) => $query->where('organization_id', $organization->getKey()))
            ->with(['venue:id,name,organization_id', 'sport:id,name'])
            ->where('is_active', true)
            ->orderBy('venue_id')
            ->orderBy('name')
            ->get()
            ->map(fn (CourtResource $resource) => [
                'id' => $resource->getKey(),
                'name' => $resource->name,
                'venue' => $resource->venue->name,
                'sport' => $resource->sport->name,
            ]);

        return Inertia::render('Owner/AvailabilityBlocks/Create', [
            'resources' => $resources,
            'timezone' => $organization->timezone,
            'defaultDate' => CarbonImmutable::now($organization->timezone)->toDateString(),
        ]);
    }

    public function store(
        StoreCourtAvailabilityBlockRequest $request,
        TenantContext $context,
        CreateCourtAvailabilityBlock $createBlock,
    ): RedirectResponse {
        $blocks = $createBlock->handle(
            $context->organization()->getKey(),
            $request->user(),
            $request->validated(),
        );
        $block = $blocks->first();
        $message = $blocks->count() === 1
            ? 'Court time blocked. Players can no longer book that period.'
            : "{$blocks->count()} recurring court times blocked.";

        return redirect()->route('owner.bookings.index', [
            'date' => $block->starts_at->setTimezone($block->timezone)->toDateString(),
        ])->with('status', $message);
    }

    public function destroy(
        Request $request,
        int $block,
        TenantContext $context,
    ): RedirectResponse {
        $organization = $context->organization();
        Gate::authorize('create', [Booking::class, $organization]);

        DB::transaction(function () use ($request, $block, $organization): void {
            $resourceId = CourtAvailabilityBlock::query()
                ->whereKey($block)
                ->where('organization_id', $organization->getKey())
                ->active()
                ->value('resource_id');

            abort_if($resourceId === null, 404);

            CourtResource::query()
                ->whereKey($resourceId)
                ->lockForUpdate()
                ->firstOrFail();

            $availabilityBlock = CourtAvailabilityBlock::query()
                ->whereKey($block)
                ->where('organization_id', $organization->getKey())
                ->active()
                ->lockForUpdate()
                ->firstOrFail();

            $availabilityBlock->update([
                'cancelled_at' => now('UTC'),
                'cancelled_by_user_id' => $request->user()->getKey(),
            ]);
        }, 5);

        return back()->with('status', 'Court time reopened for booking.');
    }
}
