<?php

namespace App\Bookings;

use App\Enums\PromotionDiscountType;
use App\Models\CourtResource;
use App\Models\Promotion;
use App\Pricing\CourtRateSchedule;

class BookingPrice
{
    public function __construct(private readonly CourtRateSchedule $rates) {}

    /**
     * @return array{
     *     unit_price: string, original_unit_price: string, total_amount: string,
     *     original_total_amount: string, discount_amount: string, currency: string,
     *     pricing_rule_snapshot: array<int, array<string, int|string|null>>|null
     * }
     */
    public function quote(
        CourtResource $resource,
        int $durationMinutes,
        ?Promotion $promotion = null,
        ?BookingWindow $window = null,
    ): array {
        $segments = $window === null
            ? [[
                'pricing_rule_id' => null,
                'name' => 'Regular price',
                'hourly_rate' => $resource->base_hourly_rate,
                'minutes' => $durationMinutes,
                'starts_at' => '',
                'ends_at' => '',
            ]]
            : $this->rates->segments($resource, $window);
        $originalTotalCents = 0;
        $totalCents = 0;

        foreach ($segments as $segment) {
            $originalRateCents = $this->moneyToCents($segment['hourly_rate']);
            $rateCents = match ($promotion?->discount_type) {
                PromotionDiscountType::Percentage => max(0, intdiv(
                    $originalRateCents * (10000 - $this->percentageBasisPoints($promotion->discount_value)) + 5000,
                    10000,
                )),
                PromotionDiscountType::FixedHourlyRate => min(
                    $originalRateCents,
                    $this->moneyToCents($promotion->discount_value),
                ),
                null => $originalRateCents,
            };
            $originalTotalCents += $this->durationTotal($originalRateCents, $segment['minutes']);
            $totalCents += $this->durationTotal($rateCents, $segment['minutes']);
        }

        $hasTimeBasedPrice = collect($segments)->contains(
            fn (array $segment) => $segment['pricing_rule_id'] !== null,
        );

        return [
            'unit_price' => $this->centsToMoney($this->effectiveHourlyRate($totalCents, $durationMinutes)),
            'original_unit_price' => $this->centsToMoney($this->effectiveHourlyRate($originalTotalCents, $durationMinutes)),
            'total_amount' => $this->centsToMoney($totalCents),
            'original_total_amount' => $this->centsToMoney($originalTotalCents),
            'discount_amount' => $this->centsToMoney($originalTotalCents - $totalCents),
            'currency' => $resource->currency,
            'pricing_rule_snapshot' => $hasTimeBasedPrice ? $segments : null,
        ];
    }

    /** @return array<string, mixed> */
    public function minimumHourlyQuote(CourtResource $resource, ?Promotion $promotion = null): array
    {
        return collect([$resource->base_hourly_rate])
            ->merge($this->rates->rulesFor($resource)->pluck('hourly_rate'))
            ->unique()
            ->map(function (string $hourlyRate) use ($resource, $promotion): array {
                $candidate = clone $resource;
                $candidate->setAttribute('base_hourly_rate', $hourlyRate);

                return $this->quote($candidate, 60, $promotion);
            })
            ->sortBy(fn (array $quote) => $this->moneyToCents($quote['total_amount']))
            ->first();
    }

    private function effectiveHourlyRate(int $totalCents, int $durationMinutes): int
    {
        return $durationMinutes > 0
            ? intdiv($totalCents * 60 + intdiv($durationMinutes, 2), $durationMinutes)
            : 0;
    }

    private function durationTotal(int $unitPriceCents, int $durationMinutes): int
    {
        return intdiv($unitPriceCents * $durationMinutes + 30, 60);
    }

    private function percentageBasisPoints(string $percentage): int
    {
        return min(10000, $this->moneyToCents($percentage));
    }

    private function moneyToCents(string $amount): int
    {
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '0');

        return (int) $whole * 100 + (int) str_pad(substr($fraction, 0, 2), 2, '0');
    }

    private function centsToMoney(int $cents): string
    {
        return sprintf('%d.%02d', intdiv($cents, 100), $cents % 100);
    }
}
