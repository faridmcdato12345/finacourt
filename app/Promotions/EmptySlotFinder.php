<?php

namespace App\Promotions;

use App\Bookings\BookingPrice;
use App\Enums\PromotionStatus;
use App\Models\Booking;
use App\Models\CourtResource;
use App\Models\Organization;
use App\Models\PromotionSlot;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class EmptySlotFinder
{
    public function __construct(private readonly BookingPrice $prices) {}

    /**
     * @return Collection<int, array{
     *   venue_id: int, venue_name: string, resource_id: int, resource_name: string,
     *   sport_id: int, sport_name: string, slot_date: string, starts_at_time: string,
     *   ends_at_time: string, lead_hours: int, is_last_minute: bool, reason: string,
     *   reason_label: string, estimated_value: string, currency: string
     * }>
     */
    public function upcoming(
        Organization $organization,
        int $horizonDays = 7,
        int $limit = 120,
        ?CarbonInterface $at = null,
    ): Collection {
        $horizonDays = min(31, max(1, $horizonDays));
        $limit = min(250, max(1, $limit));
        $timezone = $organization->timezone;
        $now = $at
            ? CarbonImmutable::instance($at)->setTimezone($timezone)
            : CarbonImmutable::now($timezone);
        $lastDay = $now->startOfDay()->addDays($horizonDays - 1);
        $utcEnd = $lastDay->endOfDay()->utc();

        $resources = CourtResource::query()
            ->where('is_active', true)
            ->whereHas('venue', fn ($query) => $query
                ->where('organization_id', $organization->getKey())
                ->where('is_published', true))
            ->with([
                'sport:id,name',
                'venue.organization:id,timezone',
                'venue.operatingHours',
                'bookings' => fn ($query) => $query
                    ->blocking($now->utc())
                    ->where('start_at', '<', $utcEnd)
                    ->where('end_at', '>', $now->utc())
                    ->getQuery(),
                'promotionSlots' => fn ($query) => $query
                    ->whereBetween('slot_date', [$now->toDateString(), $lastDay->toDateString()])
                    ->whereHas('promotion', fn ($query) => $query
                        ->where('is_active', true)
                        ->whereIn('status', [
                            PromotionStatus::Scheduled->value,
                            PromotionStatus::Active->value,
                        ])),
            ])
            ->orderBy('venue_id')
            ->orderBy('name')
            ->get();

        $opportunities = collect();

        foreach ($resources as $resource) {
            $resource->venue->setRelation('organization', $organization);
            $increment = $resource->booking_increment_minutes;
            $price = $this->prices->quote($resource, $increment);

            for ($offset = 0; $offset < $horizonDays; $offset++) {
                $date = $now->startOfDay()->addDays($offset);
                $hours = $resource->venue->operatingHours->first(
                    fn ($hours) => $hours->day_of_week->value === $date->dayOfWeek,
                );

                if (! $hours || $hours->is_closed || ! $hours->opens_at || ! $hours->closes_at) {
                    continue;
                }

                $cursor = CarbonImmutable::parse(
                    $date->toDateString().' '.substr($hours->opens_at, 0, 5),
                    $timezone,
                );
                $close = CarbonImmutable::parse(
                    $date->toDateString().' '.substr($hours->closes_at, 0, 5),
                    $timezone,
                );

                while ($cursor->addMinutes($increment)->lessThanOrEqualTo($close)) {
                    $end = $cursor->addMinutes($increment);

                    if ($cursor->greaterThan($now)
                        && ! $this->blocked($resource->bookings, $cursor, $end)
                        && ! $this->alreadyPromoted($resource->promotionSlots, $cursor, $end)) {
                        $leadHours = max(0, (int) floor($now->diffInHours($cursor)));
                        $lastMinute = $leadHours <= 24;
                        $opportunities->push([
                            'venue_id' => $resource->venue_id,
                            'venue_name' => $resource->venue->name,
                            'resource_id' => $resource->getKey(),
                            'resource_name' => $resource->name,
                            'sport_id' => $resource->sport_id,
                            'sport_name' => $resource->sport->name,
                            'slot_date' => $date->toDateString(),
                            'starts_at_time' => $cursor->format('H:i'),
                            'ends_at_time' => $end->format('H:i'),
                            'lead_hours' => $leadHours,
                            'is_last_minute' => $lastMinute,
                            'reason' => $lastMinute ? 'available_within_24_hours' : 'unsold_upcoming_slot',
                            'reason_label' => $lastMinute
                                ? 'Still available within 24 hours'
                                : 'Upcoming slot has no active reservation',
                            'estimated_value' => $price['original_total_amount'],
                            'currency' => $price['currency'],
                        ]);
                    }

                    $cursor = $cursor->addMinutes($increment);
                }
            }
        }

        return $opportunities
            ->sortBy(fn (array $slot) => sprintf(
                '%d|%s|%s|%010d',
                $slot['is_last_minute'] ? 0 : 1,
                $slot['slot_date'],
                $slot['starts_at_time'],
                $slot['resource_id'],
            ))
            ->take($limit)
            ->values();
    }

    /** @param Collection<int, Booking> $bookings */
    private function blocked(Collection $bookings, CarbonInterface $start, CarbonInterface $end): bool
    {
        return $bookings->contains(fn (Booking $booking) => $booking->start_at->lessThan($end->utc())
            && $booking->end_at->greaterThan($start->utc()));
    }

    /** @param Collection<int, PromotionSlot> $slots */
    private function alreadyPromoted(Collection $slots, CarbonInterface $start, CarbonInterface $end): bool
    {
        return $slots->contains(fn (PromotionSlot $slot) => $slot->slot_date->toDateString() === $start->toDateString()
            && $slot->starts_at_time < $end->format('H:i:s')
            && $slot->ends_at_time > $start->format('H:i:s'));
    }
}
