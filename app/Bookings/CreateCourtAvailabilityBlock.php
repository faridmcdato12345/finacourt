<?php

namespace App\Bookings;

use App\Models\Booking;
use App\Models\CourtAvailabilityBlock;
use App\Models\CourtResource;
use App\Models\User;
use Carbon\CarbonImmutable;
use DateTimeZone;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateCourtAvailabilityBlock
{
    /**
     * @param  array<string, mixed>  $data
     * @return Collection<int, CourtAvailabilityBlock>
     */
    public function handle(int $organizationId, User $creator, array $data): Collection
    {
        return DB::transaction(function () use ($organizationId, $creator, $data): Collection {
            // Use the same court-row mutex as booking creation so a booking and
            // a block cannot both win an overlap check at the same time.
            $resource = CourtResource::query()
                ->whereKey($data['resource_id'])
                ->where('is_active', true)
                ->whereHas('venue', fn ($query) => $query->where('organization_id', $organizationId))
                ->with('venue.organization')
                ->lockForUpdate()
                ->first();

            if (! $resource) {
                throw (new ModelNotFoundException)->setModel(CourtResource::class, [$data['resource_id']]);
            }

            $timezone = $resource->venue->organization->timezone;

            try {
                new DateTimeZone($timezone);
                $day = CarbonImmutable::createFromFormat('!Y-m-d', $data['block_date'], $timezone);
            } catch (\Throwable) {
                $day = false;
            }

            if (! $day || $day->format('Y-m-d') !== $data['block_date']) {
                throw ValidationException::withMessages([
                    'block_date' => 'The selected date is invalid.',
                ]);
            }

            $lastDay = $day;

            if ($data['repeat'] !== 'none') {
                try {
                    $lastDay = CarbonImmutable::createFromFormat('!Y-m-d', $data['repeat_until'], $timezone);
                } catch (\Throwable) {
                    $lastDay = false;
                }

                if (! $lastDay || $lastDay->format('Y-m-d') !== $data['repeat_until']) {
                    throw ValidationException::withMessages([
                        'repeat_until' => 'The repeat-until date is invalid.',
                    ]);
                }

                if ($lastDay->greaterThan($day->addYear())) {
                    throw ValidationException::withMessages([
                        'repeat_until' => 'Recurring court blocks can be scheduled up to one year ahead.',
                    ]);
                }
            }

            $stepDays = $data['repeat'] === 'daily' ? 1 : 7;
            $dates = collect([$day]);

            if ($data['repeat'] !== 'none') {
                $dates = collect();

                for ($date = $day; $date->lessThanOrEqualTo($lastDay); $date = $date->addDays($stepDays)) {
                    $dates->push($date);
                }
            }

            if ($dates->count() > 90) {
                throw ValidationException::withMessages([
                    'repeat_until' => 'Choose a shorter repeat period with no more than 90 occurrences.',
                ]);
            }

            $windows = $dates->map(fn (CarbonImmutable $date) => $this->window(
                $date,
                (bool) $data['is_all_day'],
                $data['start_time'],
                $data['end_time'],
                $timezone,
            ));
            $firstWindow = $windows->first();

            if ($firstWindow['ends_at']->lessThanOrEqualTo(CarbonImmutable::now($timezone))) {
                throw ValidationException::withMessages([
                    'block_date' => 'Court blocks must end in the future.',
                ]);
            }

            $rangeStart = $firstWindow['utc_start'];
            $rangeEnd = $windows->last()['utc_end'];
            $bookings = Booking::query()
                ->where('resource_id', $resource->getKey())
                ->blocking()
                ->where('start_at', '<', $rangeEnd)
                ->where('end_at', '>', $rangeStart)
                ->get(['start_at', 'end_at']);
            $existingBlocks = CourtAvailabilityBlock::query()
                ->where('resource_id', $resource->getKey())
                ->active()
                ->overlapping($rangeStart, $rangeEnd)
                ->get(['starts_at', 'ends_at', 'reason']);

            foreach ($windows as $window) {
                $bookingConflict = $bookings->contains(fn (Booking $booking) => $booking->start_at->lessThan($window['utc_end'])
                    && $booking->end_at->greaterThan($window['utc_start']));

                if ($bookingConflict) {
                    throw ValidationException::withMessages([
                        'start_time' => "The block on {$window['date']} overlaps an active reservation or hold. Cancel or move that booking first.",
                    ]);
                }

                $existingBlock = $existingBlocks->first(fn (CourtAvailabilityBlock $block) => $block->starts_at->lessThan($window['utc_end'])
                    && $block->ends_at->greaterThan($window['utc_start']));

                if ($existingBlock) {
                    throw ValidationException::withMessages([
                        'start_time' => "The block on {$window['date']} overlaps another court block: {$existingBlock->reason}",
                    ]);
                }
            }

            $seriesToken = $windows->count() > 1 ? (string) Str::ulid() : null;

            return $windows->map(fn (array $window) => CourtAvailabilityBlock::query()->create([
                'organization_id' => $organizationId,
                'venue_id' => $resource->venue_id,
                'resource_id' => $resource->getKey(),
                'starts_at' => $window['utc_start'],
                'ends_at' => $window['utc_end'],
                'timezone' => $timezone,
                'is_all_day' => (bool) $data['is_all_day'],
                'reason' => $data['reason'],
                'series_token' => $seriesToken,
                'created_by_user_id' => $creator->getKey(),
            ]));
        }, 5);
    }

    /** @return array{date: string, starts_at: CarbonImmutable, ends_at: CarbonImmutable, utc_start: CarbonImmutable, utc_end: CarbonImmutable} */
    private function window(
        CarbonImmutable $day,
        bool $allDay,
        ?string $startTime,
        ?string $endTime,
        string $timezone,
    ): array {
        if ($allDay) {
            $startsAt = $day->startOfDay();
            $endsAt = $startsAt->addDay();
        } else {
            $startsAt = $this->localDateTime($day->toDateString(), (string) $startTime, $timezone, 'start_time');
            $endsAt = $this->localDateTime($day->toDateString(), (string) $endTime, $timezone, 'end_time');
        }

        if ($endsAt->lessThanOrEqualTo($startsAt)) {
            throw ValidationException::withMessages([
                'end_time' => 'The end time must be later than the start time on the same day.',
            ]);
        }

        return [
            'date' => $day->toDateString(),
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'utc_start' => $startsAt->utc(),
            'utc_end' => $endsAt->utc(),
        ];
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
}
