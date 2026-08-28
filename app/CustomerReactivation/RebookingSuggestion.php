<?php

namespace App\CustomerReactivation;

use App\Bookings\AvailabilityService;
use App\Models\Booking;
use App\Models\ReactivationCampaign;
use App\Models\User;
use Carbon\CarbonImmutable;

class RebookingSuggestion
{
    public function __construct(
        private readonly CustomerBookingHistory $history,
        private readonly AvailabilityService $availability,
    ) {}

    /** @return array{resource_id: int, date: string, start_time: string, duration_minutes: int}|null */
    public function for(ReactivationCampaign $campaign, User $user): ?array
    {
        $bookings = $this->history->qualifying($campaign->organization)
            ->where('player_user_id', $user->getKey())
            ->where('venue_id', $campaign->venue_id)
            ->when($campaign->sport_id, fn ($query) => $query
                ->whereHas('resource', fn ($resource) => $resource->where('sport_id', $campaign->sport_id)))
            ->with('resource.venue.organization')
            ->latest('end_at')
            ->limit(20)
            ->get();

        if ($bookings->isEmpty()) {
            return null;
        }

        $patterns = $bookings->groupBy(function (Booking $booking): string {
            $local = $booking->start_at->setTimezone($booking->timezone);

            return $local->dayOfWeek.'-'.$local->format('H:i');
        })->sortByDesc(fn ($group) => $group->count());
        $pattern = $patterns->keys()->first();
        $habitual = $bookings->count() >= 3 && $patterns->first()->count() >= 2;
        $seed = $habitual ? $patterns->first()->first() : $bookings->first();
        $localStart = $seed->start_at->setTimezone($seed->timezone);
        $weekday = $habitual ? (int) explode('-', $pattern, 2)[0] : $localStart->dayOfWeek;
        $startTime = $habitual ? explode('-', $pattern, 2)[1] : $localStart->format('H:i');
        $duration = max(30, $seed->start_at->diffInMinutes($seed->end_at));
        $resource = $seed->resource;

        if (! $resource?->is_active) {
            return null;
        }

        $today = CarbonImmutable::now($campaign->organization->timezone)->startOfDay();

        for ($offset = 1; $offset <= (int) config('reactivation.suggestion_horizon_days', 28); $offset++) {
            $candidate = $today->addDays($offset);

            if ($candidate->dayOfWeek !== $weekday) {
                continue;
            }

            try {
                $window = $this->availability->window(
                    $resource,
                    $candidate->toDateString(),
                    $startTime,
                    CarbonImmutable::createFromFormat('H:i', $startTime)->addMinutes($duration)->format('H:i'),
                );
                $this->availability->ensureBookable($resource, $window);

                if (! $this->availability->hasConflict($resource->getKey(), $window->utcStart, $window->utcEnd)) {
                    return [
                        'resource_id' => $resource->getKey(),
                        'date' => $candidate->toDateString(),
                        'start_time' => $startTime,
                        'duration_minutes' => $duration,
                    ];
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
    }
}
