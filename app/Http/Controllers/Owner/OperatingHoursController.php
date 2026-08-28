<?php

namespace App\Http\Controllers\Owner;

use App\Enums\Weekday;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateOperatingHoursRequest;
use App\Models\CourtResource;
use App\Models\Venue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class OperatingHoursController extends Controller
{
    public function edit(Venue $venue): Response
    {
        Gate::authorize('update', $venue);
        $hours = $venue->operatingHours()->get()->keyBy(fn ($hour) => $hour->day_of_week->value);

        return Inertia::render('Owner/Venues/Hours', [
            'venue' => ['id' => $venue->getKey(), 'name' => $venue->name],
            'hours' => collect(Weekday::cases())->map(function (Weekday $day) use ($hours) {
                $hour = $hours->get($day->value);

                return [
                    'day_of_week' => $day->value,
                    'day' => $day->label(),
                    'is_closed' => $hour?->is_closed ?? false,
                    'opens_at' => $hour?->opens_at ? substr($hour->opens_at, 0, 5) : '08:00',
                    'closes_at' => $hour?->closes_at ? substr($hour->closes_at, 0, 5) : '22:00',
                ];
            }),
        ]);
    }

    public function update(UpdateOperatingHoursRequest $request, Venue $venue): RedirectResponse
    {
        DB::transaction(function () use ($request, $venue): void {
            CourtResource::query()
                ->where('venue_id', $venue->getKey())
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id']);

            foreach ($request->validated('hours') as $hour) {
                $isClosed = (bool) $hour['is_closed'];

                $venue->operatingHours()->updateOrCreate(
                    ['day_of_week' => $hour['day_of_week']],
                    [
                        'is_closed' => $isClosed,
                        'opens_at' => $isClosed ? null : $hour['opens_at'],
                        'closes_at' => $isClosed ? null : $hour['closes_at'],
                    ],
                );
            }
        });

        return redirect()->route('owner.venues.show', $venue)
            ->with('status', 'Operating hours updated.');
    }
}
