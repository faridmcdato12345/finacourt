<?php

namespace App\Http\Controllers\Owner;

use App\Enums\ResourceSetting;
use App\Enums\ResourceType;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCourtResourceRequest;
use App\Http\Requests\UpdateCourtResourceRequest;
use App\Models\CourtResource;
use App\Models\Venue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CourtResourceController extends Controller
{
    public function create(Venue $venue): Response
    {
        Gate::authorize('create', [CourtResource::class, $venue]);

        return Inertia::render('Owner/Resources/Create', [
            'venue' => ['id' => $venue->getKey(), 'name' => $venue->name],
            ...$this->formOptions($venue),
        ]);
    }

    public function store(StoreCourtResourceRequest $request, Venue $venue): RedirectResponse
    {
        $venue->resources()->create([
            ...$request->validated(),
            'currency' => 'PHP',
        ]);

        return redirect()->route('owner.venues.show', $venue)
            ->with('status', 'Court created with its normal price.');
    }

    public function edit(Venue $venue, CourtResource $resource): Response
    {
        $this->ensureNestedResource($venue, $resource);
        Gate::authorize('update', $resource);

        return Inertia::render('Owner/Resources/Edit', [
            'venue' => ['id' => $venue->getKey(), 'name' => $venue->name],
            'resource' => [
                'id' => $resource->getKey(),
                'name' => $resource->name,
                'sport_id' => $resource->sport_id,
                'resource_type' => $resource->resource_type->value,
                'setting' => $resource->setting->value,
                'is_active' => $resource->is_active,
                'base_hourly_rate' => $resource->base_hourly_rate,
                'booking_increment_minutes' => $resource->booking_increment_minutes,
            ],
            ...$this->formOptions($venue),
        ]);
    }

    public function update(
        UpdateCourtResourceRequest $request,
        Venue $venue,
        CourtResource $resource,
    ): RedirectResponse {
        $resource->update($request->validated());

        return redirect()->route('owner.venues.show', $venue)
            ->with('status', 'Court details and price updated.');
    }

    public function destroy(Venue $venue, CourtResource $resource): RedirectResponse
    {
        $this->ensureNestedResource($venue, $resource);
        Gate::authorize('delete', $resource);

        $deleted = DB::transaction(function () use ($resource): bool {
            $lockedResource = CourtResource::query()->whereKey($resource->getKey())->lockForUpdate()->firstOrFail();

            if ($lockedResource->bookings()->exists()) {
                return false;
            }

            $lockedResource->delete();

            return true;
        });

        if (! $deleted) {
            return back()->with('status', 'Courts with booking history cannot be deleted. Turn bookings off for this court instead.');
        }

        return redirect()->route('owner.venues.show', $venue)
            ->with('status', 'Court deleted.');
    }

    private function ensureNestedResource(Venue $venue, CourtResource $resource): void
    {
        abort_unless($resource->venue_id === $venue->getKey(), 404);
    }

    /** @return array{sports: mixed, resourceTypes: array, settings: array, increments: array<int, int>} */
    private function formOptions(Venue $venue): array
    {
        return [
            'sports' => $venue->sports()->where('is_active', true)->orderBy('name')->get(['sports.id', 'sports.name']),
            'resourceTypes' => array_map(
                fn (ResourceType $type) => ['value' => $type->value, 'label' => $type->label()],
                ResourceType::cases(),
            ),
            'settings' => array_map(
                fn (ResourceSetting $setting) => ['value' => $setting->value, 'label' => $setting->label()],
                ResourceSetting::cases(),
            ),
            'increments' => [15, 30, 45, 60, 90, 120],
        ];
    }
}
