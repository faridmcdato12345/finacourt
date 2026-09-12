<?php

namespace App\Pricing;

use App\Bookings\BookingWindow;
use App\Models\CourtPricingRule;
use App\Models\CourtResource;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class CourtRateSchedule
{
    /**
     * @return array<int, array{
     *     pricing_rule_id: int|null, name: string, hourly_rate: string,
     *     minutes: int, starts_at: string, ends_at: string
     * }>
     */
    public function segments(CourtResource $resource, BookingWindow $window): array
    {
        $resource->loadMissing('pricingRules');
        $rules = $resource->pricingRules
            ->filter(fn (CourtPricingRule $rule) => in_array(
                $window->localStart->dayOfWeek,
                array_map('intval', $rule->days_of_week),
                true,
            ))
            ->filter(function (CourtPricingRule $rule) use ($window): bool {
                [$start, $end] = $this->ruleWindow($rule, $window->localStart);

                return $start->lessThan($window->localEnd) && $end->greaterThan($window->localStart);
            })
            ->values();

        $boundaries = collect([$window->localStart, $window->localEnd]);

        foreach ($rules as $rule) {
            [$start, $end] = $this->ruleWindow($rule, $window->localStart);

            if ($start->greaterThan($window->localStart) && $start->lessThan($window->localEnd)) {
                $boundaries->push($start);
            }

            if ($end->greaterThan($window->localStart) && $end->lessThan($window->localEnd)) {
                $boundaries->push($end);
            }
        }

        $boundaries = $boundaries
            ->unique(fn (CarbonImmutable $boundary) => $boundary->getTimestamp())
            ->sortBy(fn (CarbonImmutable $boundary) => $boundary->getTimestamp())
            ->values();

        return $boundaries
            ->slice(0, -1)
            ->values()
            ->map(function (CarbonImmutable $start, int $index) use ($boundaries, $resource, $rules): array {
                /** @var CarbonImmutable $end */
                $end = $boundaries[$index + 1];
                $rule = $rules->first(function (CourtPricingRule $rule) use ($start, $end): bool {
                    [$ruleStart, $ruleEnd] = $this->ruleWindow($rule, $start);

                    return $ruleStart->lessThanOrEqualTo($start) && $ruleEnd->greaterThanOrEqualTo($end);
                });

                return [
                    'pricing_rule_id' => $rule?->getKey(),
                    'name' => $rule?->name ?? 'Regular price',
                    'hourly_rate' => $rule?->hourly_rate ?? $resource->base_hourly_rate,
                    'minutes' => (int) $start->diffInMinutes($end),
                    'starts_at' => $start->format('H:i'),
                    'ends_at' => $end->format('H:i'),
                ];
            })
            ->all();
    }

    /** @return Collection<int, CourtPricingRule> */
    public function rulesFor(CourtResource $resource): Collection
    {
        $resource->loadMissing('pricingRules');

        return $resource->pricingRules;
    }

    /** @return array{CarbonImmutable, CarbonImmutable} */
    private function ruleWindow(CourtPricingRule $rule, CarbonImmutable $date): array
    {
        $day = $date->startOfDay();

        return [
            $day->addSeconds($this->seconds($rule->starts_at_time)),
            $day->addSeconds($this->seconds($rule->ends_at_time)),
        ];
    }

    private function seconds(string $time): int
    {
        [$hour, $minute, $second] = array_map('intval', array_pad(explode(':', $time), 3, 0));

        return $hour * 3600 + $minute * 60 + $second;
    }
}
