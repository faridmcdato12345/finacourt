<?php

namespace App\Bookings;

use App\Models\Booking;
use App\Models\CourtResource;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DateTimeZone;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class AvailabilityService
{
    public function window(
        CourtResource $resource,
        string $date,
        string $startTime,
        string $endTime,
        bool $requireFuture = true,
    ): BookingWindow {
        $resource->loadMissing('venue.organization');
        $timezone = $this->timezone($resource);
        $localStart = $this->localDateTime($date, $startTime, $timezone, 'start_time');
        $localEnd = $this->localDateTime($date, $endTime, $timezone, 'end_time');

        if ($localEnd->lessThanOrEqualTo($localStart)) {
            throw ValidationException::withMessages([
                'end_time' => 'The end time must be later than the start time on the same day.',
            ]);
        }

        if ($requireFuture && $localStart->lessThanOrEqualTo(CarbonImmutable::now($timezone))) {
            throw ValidationException::withMessages([
                'start_time' => 'Bookings must start in the future.',
            ]);
        }

        return new BookingWindow(
            localStart: $localStart,
            localEnd: $localEnd,
            utcStart: $localStart->utc(),
            utcEnd: $localEnd->utc(),
            durationMinutes: (int) $localStart->diffInMinutes($localEnd),
        );
    }

    public function ensureBookable(CourtResource $resource, BookingWindow $window): void
    {
        if (! $resource->is_active) {
            throw ValidationException::withMessages([
                'resource_id' => 'This resource is inactive and cannot accept bookings.',
            ]);
        }

        $hours = $resource->venue->relationLoaded('operatingHours')
            ? $resource->venue->operatingHours->first(
                fn ($hours) => $hours->day_of_week->value === $window->localStart->dayOfWeek,
            )
            : $resource->venue->operatingHours()
                ->where('day_of_week', $window->localStart->dayOfWeek)
                ->first();

        if (! $hours || $hours->is_closed || ! $hours->opens_at || ! $hours->closes_at) {
            throw ValidationException::withMessages([
                'booking_date' => 'The venue is closed on the selected date.',
            ]);
        }

        $openMinute = $this->minutes($hours->opens_at);
        $closeMinute = $this->minutes($hours->closes_at);
        $startMinute = $window->localStart->hour * 60 + $window->localStart->minute;
        $endMinute = $window->localEnd->hour * 60 + $window->localEnd->minute;

        if ($startMinute < $openMinute || $endMinute > $closeMinute) {
            throw ValidationException::withMessages([
                'start_time' => 'The booking must be entirely within venue operating hours.',
            ]);
        }

        $increment = $resource->booking_increment_minutes;

        if (
            ($startMinute - $openMinute) % $increment !== 0
            || ($endMinute - $openMinute) % $increment !== 0
            || $window->durationMinutes % $increment !== 0
        ) {
            throw ValidationException::withMessages([
                'start_time' => "Start, end, and duration must align to {$increment}-minute slots from opening time.",
            ]);
        }
    }

    public function hasConflict(
        int $resourceId,
        CarbonInterface $startAt,
        CarbonInterface $endAt,
        ?CarbonInterface $at = null,
    ): bool {
        return Booking::query()
            ->where('resource_id', $resourceId)
            ->blocking($at)
            ->where('start_at', '<', $endAt)
            ->where('end_at', '>', $startAt)
            ->exists();
    }

    /** @return array{date: string, timezone: string, is_open: bool, opens_at: ?string, closes_at: ?string, duration_minutes: int, slots: Collection<int, array{start_time: string, end_time: string, available: bool}>} */
    public function slots(CourtResource $resource, string $date, int $durationMinutes): array
    {
        $resource->loadMissing('venue.organization');
        $timezone = $this->timezone($resource);
        $day = $this->localDateTime($date, '00:00', $timezone, 'date');
        $increment = $resource->booking_increment_minutes;

        if ($durationMinutes <= 0 || $durationMinutes % $increment !== 0) {
            throw ValidationException::withMessages([
                'duration_minutes' => "Duration must be a positive multiple of {$increment} minutes.",
            ]);
        }

        $hours = $resource->venue->operatingHours()
            ->where('day_of_week', $day->dayOfWeek)
            ->first();

        if (! $resource->is_active || ! $hours || $hours->is_closed || ! $hours->opens_at || ! $hours->closes_at) {
            return $this->emptySchedule($date, $timezone, $durationMinutes);
        }

        $open = $this->localDateTime($date, substr($hours->opens_at, 0, 5), $timezone, 'date');
        $close = $this->localDateTime($date, substr($hours->closes_at, 0, 5), $timezone, 'date');
        $blockers = Booking::query()
            ->where('resource_id', $resource->getKey())
            ->blocking()
            ->where('start_at', '<', $close->utc())
            ->where('end_at', '>', $open->utc())
            ->get(['start_at', 'end_at']);

        $slots = collect();
        $cursor = $open;
        $now = CarbonImmutable::now($timezone);

        while ($cursor->addMinutes($durationMinutes)->lessThanOrEqualTo($close)) {
            $end = $cursor->addMinutes($durationMinutes);
            $available = $cursor->isFuture() && ! $blockers->contains(
                fn (Booking $booking) => $booking->start_at->lessThan($end->utc())
                    && $booking->end_at->greaterThan($cursor->utc()),
            );

            $slots->push([
                'start_time' => $cursor->format('H:i'),
                'end_time' => $end->format('H:i'),
                'available' => $available && $cursor->greaterThan($now),
            ]);
            $cursor = $cursor->addMinutes($increment);
        }

        return [
            'date' => $date,
            'timezone' => $timezone,
            'is_open' => true,
            'opens_at' => $open->format('H:i'),
            'closes_at' => $close->format('H:i'),
            'duration_minutes' => $durationMinutes,
            'slots' => $slots,
        ];
    }

    private function timezone(CourtResource $resource): string
    {
        $timezone = $resource->venue->organization->timezone;

        try {
            new DateTimeZone($timezone);
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'resource_id' => 'The venue timezone is not valid.',
            ]);
        }

        return $timezone;
    }

    private function localDateTime(string $date, string $time, string $timezone, string $field): CarbonImmutable
    {
        $input = "$date $time";

        try {
            $value = CarbonImmutable::createFromFormat('!Y-m-d H:i', $input, $timezone);
        } catch (\Throwable) {
            $value = false;
        }

        if (! $value || $value->format('Y-m-d H:i') !== $input) {
            throw ValidationException::withMessages([$field => 'The selected date or time is invalid.']);
        }

        return $value;
    }

    private function minutes(string $time): int
    {
        [$hour, $minute] = array_map('intval', explode(':', $time));

        return $hour * 60 + $minute;
    }

    /** @return array{date: string, timezone: string, is_open: false, opens_at: null, closes_at: null, duration_minutes: int, slots: Collection<int, never>} */
    private function emptySchedule(string $date, string $timezone, int $durationMinutes): array
    {
        return [
            'date' => $date,
            'timezone' => $timezone,
            'is_open' => false,
            'opens_at' => null,
            'closes_at' => null,
            'duration_minutes' => $durationMinutes,
            'slots' => collect(),
        ];
    }
}
