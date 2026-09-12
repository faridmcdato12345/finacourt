<?php

namespace App\Bookings;

use App\Models\Booking;
use App\Models\CourtAvailabilityBlock;
use App\Models\CourtResource;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DateTimeZone;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class AvailabilityService
{
    public function __construct(private readonly BookingPrice $prices) {}

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

        if ($localEnd->lessThan($localStart)) {
            $localEnd = $localEnd->addDay();
        }

        if ($localEnd->equalTo($localStart)) {
            throw ValidationException::withMessages([
                'end_time' => 'The end time must be different from the start time.',
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

        $operatingWindow = $this->operatingWindows(
            $resource,
            $window->localStart->startOfDay()->subDay(),
            $window->localEnd->startOfDay(),
        )->first(fn (array $hours) => $hours['start']->lessThanOrEqualTo($window->localStart)
            && $hours['end']->greaterThanOrEqualTo($window->localEnd));

        if (! $operatingWindow) {
            throw ValidationException::withMessages([
                'start_time' => 'The booking must be entirely within venue operating hours.',
            ]);
        }

        $increment = $resource->booking_increment_minutes;
        $alignsWithOpening = collect($operatingWindow['anchors'])->contains(
            fn (CarbonImmutable $opening) => $opening->lessThanOrEqualTo($window->localStart)
                && (int) $opening->diffInMinutes($window->localStart) % $increment === 0
                && (int) $opening->diffInMinutes($window->localEnd) % $increment === 0,
        );

        if (! $alignsWithOpening || $window->durationMinutes % $increment !== 0) {
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
        return $this->hasBookingConflict($resourceId, $startAt, $endAt, $at)
            || $this->hasAvailabilityBlockConflict($resourceId, $startAt, $endAt);
    }

    public function hasBookingConflict(
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

    public function hasAvailabilityBlockConflict(
        int $resourceId,
        CarbonInterface $startAt,
        CarbonInterface $endAt,
    ): bool {
        return CourtAvailabilityBlock::query()
            ->where('resource_id', $resourceId)
            ->active()
            ->overlapping($startAt, $endAt)
            ->exists();
    }

    /** @return array<string, mixed> */
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

        if (! $resource->is_active) {
            return $this->emptySchedule($date, $timezone, $durationMinutes);
        }

        $nextDay = $day->addDay();
        $operatingWindows = $this->operatingWindows($resource, $day->subDay(), $day->addDay());
        $relevantWindows = $operatingWindows
            ->filter(fn (array $hours) => $hours['start']->lessThan($nextDay)
                && $hours['end']->greaterThan($day))
            ->values();

        if ($relevantWindows->isEmpty()) {
            return $this->emptySchedule($date, $timezone, $durationMinutes);
        }

        $queryEnd = $relevantWindows
            ->sortBy(fn (array $hours) => $hours['end']->getTimestamp())
            ->last()['end'];
        $blockers = Booking::query()
            ->where('resource_id', $resource->getKey())
            ->blocking()
            ->where('start_at', '<', $queryEnd->utc())
            ->where('end_at', '>', $day->utc())
            ->get(['start_at', 'end_at']);
        $courtBlocks = CourtAvailabilityBlock::query()
            ->where('resource_id', $resource->getKey())
            ->active()
            ->overlapping($day->utc(), $queryEnd->utc())
            ->get(['starts_at', 'ends_at']);

        $slots = collect();
        $now = CarbonImmutable::now($timezone);

        foreach ($relevantWindows as $operatingWindow) {
            foreach ($operatingWindow['anchors'] as $opening) {
                $cursor = $opening;

                if ($cursor->lessThan($day)) {
                    $minutesUntilDay = (int) $cursor->diffInMinutes($day);
                    $cursor = $cursor->addMinutes((int) ceil($minutesUntilDay / $increment) * $increment);
                }

                while ($cursor->lessThan($nextDay)
                    && $cursor->addMinutes($durationMinutes)->lessThanOrEqualTo($operatingWindow['end'])) {
                    $end = $cursor->addMinutes($durationMinutes);
                    $key = (string) $cursor->getTimestamp();

                    if ($slots->has($key)) {
                        $cursor = $cursor->addMinutes($increment);

                        continue;
                    }

                    $window = new BookingWindow(
                        localStart: $cursor,
                        localEnd: $end,
                        utcStart: $cursor->utc(),
                        utcEnd: $end->utc(),
                        durationMinutes: $durationMinutes,
                    );
                    $price = $this->prices->quote($resource, $durationMinutes, window: $window);
                    $available = $cursor->greaterThan($now) && ! $blockers->contains(
                        fn (Booking $booking) => $booking->start_at->lessThan($end->utc())
                            && $booking->end_at->greaterThan($cursor->utc()),
                    ) && ! $courtBlocks->contains(
                        fn (CourtAvailabilityBlock $block) => $block->starts_at->lessThan($end->utc())
                            && $block->ends_at->greaterThan($cursor->utc()),
                    );

                    $slots->put($key, [
                        'booking_date' => $cursor->toDateString(),
                        'end_date' => $end->toDateString(),
                        'start_time' => $cursor->format('H:i'),
                        'end_time' => $end->format('H:i'),
                        'display_time' => $cursor->format('H:i').'–'.($end->isSameDay($cursor)
                            ? $end->format('H:i')
                            : $end->format('D H:i')),
                        'display_time_12_hour' => $cursor->format('g:i A').'–'.($end->isSameDay($cursor)
                            ? $end->format('g:i A')
                            : $end->format('D g:i A')),
                        'start_label' => $cursor->format('M j, H:i'),
                        'end_label' => $end->isSameDay($cursor)
                            ? $end->format('H:i')
                            : $end->format('M j, H:i'),
                        'start_label_12_hour' => $cursor->format('M j, g:i A'),
                        'end_label_12_hour' => $end->isSameDay($cursor)
                            ? $end->format('g:i A')
                            : $end->format('M j, g:i A'),
                        'start_offset_minutes' => (int) $day->diffInMinutes($cursor),
                        'end_offset_minutes' => (int) $day->diffInMinutes($end),
                        'available' => $available,
                        'unit_price' => $price['unit_price'],
                        'total_amount' => $price['total_amount'],
                        'has_time_based_price' => $price['pricing_rule_snapshot'] !== null,
                    ]);
                    $cursor = $cursor->addMinutes($increment);
                }
            }
        }

        $slots = $slots->sortBy('start_offset_minutes')->values();
        $periods = $relevantWindows->map(function (array $hours) use ($day, $nextDay): array {
            $start = $hours['start']->greaterThan($day) ? $hours['start'] : $day;
            $end = $hours['end']->lessThan($nextDay) ? $hours['end'] : $nextDay;

            return [
                'starts_at' => $start->format('H:i'),
                'ends_at' => $end->equalTo($nextDay) ? '24:00' : $end->format('H:i'),
            ];
        })->values();
        $isOpen24Hours = $periods->count() === 1
            && $periods->first()['starts_at'] === '00:00'
            && $periods->first()['ends_at'] === '24:00';

        return [
            'date' => $date,
            'timezone' => $timezone,
            'is_open' => true,
            'is_24_hours' => $isOpen24Hours,
            'opens_at' => $periods->first()['starts_at'],
            'closes_at' => $periods->last()['ends_at'],
            'hours_label' => $isOpen24Hours
                ? 'Open 24 hours'
                : $periods->map(fn (array $period) => $period['starts_at'].'–'.$period['ends_at'])->join(' · '),
            'hours_label_12_hour' => $isOpen24Hours
                ? 'Open 24 hours'
                : $periods->map(fn (array $period) => $this->displayClockTime($period['starts_at'])
                    .'–'.$this->displayClockTime($period['ends_at']))->join(' · '),
            'periods' => $periods,
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

    /**
     * @return Collection<int, array{
     *     start: CarbonImmutable,
     *     end: CarbonImmutable,
     *     anchors: array<int, CarbonImmutable>
     * }>
     */
    private function operatingWindows(
        CourtResource $resource,
        CarbonImmutable $firstDay,
        CarbonImmutable $lastDay,
    ): Collection {
        $resource->venue->loadMissing('operatingHours');
        $hoursByDay = $resource->venue->operatingHours
            ->keyBy(fn ($hours) => $hours->day_of_week->value);
        $windows = collect();
        $date = $firstDay->startOfDay();
        $lastDate = $lastDay->startOfDay();

        while ($date->lessThanOrEqualTo($lastDate)) {
            $hours = $hoursByDay->get($date->dayOfWeek);

            if ($hours && ! $hours->is_closed && $hours->opens_at && $hours->closes_at) {
                $open = $date->addSeconds($this->seconds($hours->opens_at));
                $close = $date->addSeconds($this->seconds($hours->closes_at));

                if ($close->lessThanOrEqualTo($open)) {
                    $close = $close->addDay();
                }

                $windows->push([
                    'start' => $open,
                    'end' => $close,
                    'anchors' => [$open],
                ]);
            }

            $date = $date->addDay();
        }

        $merged = collect();

        foreach ($windows->sortBy(fn (array $hours) => $hours['start']->getTimestamp()) as $window) {
            if ($merged->isEmpty()) {
                $merged->push($window);

                continue;
            }

            $previous = $merged->pop();

            if ($window['start']->lessThanOrEqualTo($previous['end'])) {
                $previous['end'] = $window['end']->greaterThan($previous['end'])
                    ? $window['end']
                    : $previous['end'];
                $previous['anchors'] = [...$previous['anchors'], ...$window['anchors']];
                $merged->push($previous);
            } else {
                $merged->push($previous, $window);
            }
        }

        return $merged->values();
    }

    private function seconds(string $time): int
    {
        [$hour, $minute, $second] = array_map('intval', array_pad(explode(':', $time), 3, 0));

        return $hour * 3600 + $minute * 60 + $second;
    }

    private function displayClockTime(string $time): string
    {
        [$hour, $minute] = array_map('intval', explode(':', $time));
        $suffix = $hour >= 12 && $hour < 24 ? 'PM' : 'AM';
        $displayHour = $hour % 12;

        return ($displayHour === 0 ? 12 : $displayHour).':'.str_pad((string) $minute, 2, '0', STR_PAD_LEFT).' '.$suffix;
    }

    /** @return array<string, mixed> */
    private function emptySchedule(string $date, string $timezone, int $durationMinutes): array
    {
        return [
            'date' => $date,
            'timezone' => $timezone,
            'is_open' => false,
            'is_24_hours' => false,
            'opens_at' => null,
            'closes_at' => null,
            'hours_label' => null,
            'hours_label_12_hour' => null,
            'periods' => collect(),
            'duration_minutes' => $durationMinutes,
            'slots' => collect(),
        ];
    }
}
