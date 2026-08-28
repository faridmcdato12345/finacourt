<?php

namespace App\Bookings;

use App\Enums\PromotionDiscountType;
use App\Models\CourtResource;
use App\Models\Promotion;

class BookingPrice
{
    /** @return array{unit_price: string, original_unit_price: string, total_amount: string, original_total_amount: string, discount_amount: string, currency: string} */
    public function quote(CourtResource $resource, int $durationMinutes, ?Promotion $promotion = null): array
    {
        $originalUnitCents = $this->moneyToCents($resource->base_hourly_rate);
        $unitPriceCents = match ($promotion?->discount_type) {
            PromotionDiscountType::Percentage => max(0, intdiv(
                $originalUnitCents * (10000 - $this->percentageBasisPoints($promotion->discount_value)) + 5000,
                10000,
            )),
            PromotionDiscountType::FixedHourlyRate => min(
                $originalUnitCents,
                $this->moneyToCents($promotion->discount_value),
            ),
            null => $originalUnitCents,
        };
        $originalTotalCents = $this->durationTotal($originalUnitCents, $durationMinutes);
        $totalCents = $this->durationTotal($unitPriceCents, $durationMinutes);

        return [
            'unit_price' => $this->centsToMoney($unitPriceCents),
            'original_unit_price' => $this->centsToMoney($originalUnitCents),
            'total_amount' => $this->centsToMoney($totalCents),
            'original_total_amount' => $this->centsToMoney($originalTotalCents),
            'discount_amount' => $this->centsToMoney($originalTotalCents - $totalCents),
            'currency' => $resource->currency,
        ];
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
