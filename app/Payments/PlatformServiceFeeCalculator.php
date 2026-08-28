<?php

namespace App\Payments;

use App\Enums\PlatformServiceFeeType;
use App\Models\PlatformServiceFeeRule;
use Carbon\CarbonInterface;

class PlatformServiceFeeCalculator
{
    /**
     * @return array{
     *     platform_service_fee_rule_id: int|null,
     *     platform_service_fee_name: string|null,
     *     platform_service_fee_type: string|null,
     *     platform_service_fee_rate_basis_points: int|null,
     *     platform_service_fee_fixed_amount: string|null,
     *     platform_service_fee_amount: string,
     *     player_total_amount: string
     * }
     */
    public function quote(string $venueAmount, string $currency = 'PHP', ?CarbonInterface $at = null): array
    {
        $venueCents = $this->moneyToCents($venueAmount);
        $rule = $this->activeRule($currency, $at);

        if (! $rule) {
            return $this->emptyQuote($venueCents);
        }

        $feeCents = match ($rule->fee_type) {
            PlatformServiceFeeType::Percentage => intdiv(
                $venueCents * (int) $rule->percentage_basis_points + 5000,
                10000,
            ),
            PlatformServiceFeeType::Fixed => $this->moneyToCents((string) $rule->fixed_amount),
        };

        $minimum = $this->moneyToCents((string) $rule->minimum_fee_amount);
        $maximum = $rule->maximum_fee_amount === null
            ? null
            : $this->moneyToCents((string) $rule->maximum_fee_amount);

        $feeCents = max($feeCents, $minimum);

        if ($maximum !== null) {
            $feeCents = min($feeCents, $maximum);
        }

        $feeCents = max(0, $feeCents);

        return [
            'platform_service_fee_rule_id' => $rule->getKey(),
            'platform_service_fee_name' => $rule->name,
            'platform_service_fee_type' => $rule->fee_type->value,
            'platform_service_fee_rate_basis_points' => $rule->percentage_basis_points,
            'platform_service_fee_fixed_amount' => $rule->fixed_amount,
            'platform_service_fee_amount' => $this->centsToMoney($feeCents),
            'player_total_amount' => $this->centsToMoney($venueCents + $feeCents),
        ];
    }

    public function activeRule(string $currency = 'PHP', ?CarbonInterface $at = null): ?PlatformServiceFeeRule
    {
        return PlatformServiceFeeRule::query()
            ->where('currency', strtoupper($currency))
            ->effective($at)
            ->latest('id')
            ->first();
    }

    /**
     * @return array{
     *     platform_service_fee_rule_id: null,
     *     platform_service_fee_name: null,
     *     platform_service_fee_type: null,
     *     platform_service_fee_rate_basis_points: null,
     *     platform_service_fee_fixed_amount: null,
     *     platform_service_fee_amount: string,
     *     player_total_amount: string
     * }
     */
    public function emptyQuoteFromAmount(string $venueAmount): array
    {
        return $this->emptyQuote($this->moneyToCents($venueAmount));
    }

    /** @return array<string, string|int|null> */
    private function emptyQuote(int $venueCents): array
    {
        return [
            'platform_service_fee_rule_id' => null,
            'platform_service_fee_name' => null,
            'platform_service_fee_type' => null,
            'platform_service_fee_rate_basis_points' => null,
            'platform_service_fee_fixed_amount' => null,
            'platform_service_fee_amount' => '0.00',
            'player_total_amount' => $this->centsToMoney($venueCents),
        ];
    }

    private function moneyToCents(string $amount): int
    {
        if (preg_match('/^(0|[1-9]\d*)(?:\.(\d{1,2}))?$/', $amount, $matches) !== 1) {
            throw new \InvalidArgumentException("Invalid money amount [{$amount}].");
        }

        return ((int) $matches[1] * 100) + (int) str_pad($matches[2] ?? '0', 2, '0');
    }

    private function centsToMoney(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
