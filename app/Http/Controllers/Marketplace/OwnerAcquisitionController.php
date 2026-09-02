<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\CourtResource;
use App\Models\PlatformServiceFeeRule;
use App\Models\Venue;
use App\Payments\PlatformServiceFeeCalculator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;

class OwnerAcquisitionController extends Controller
{
    public function show(PlatformServiceFeeCalculator $serviceFees): View
    {
        return view('marketplace.owners', [
            'supply' => $this->publicSupply(),
            'pricing' => $this->ownerPricing($serviceFees),
            'seo' => [
                'title' => 'Get more players and court bookings with FinACourt',
                'description' => 'Help nearby players discover your venue, turn empty court hours into bookable deals, bring past customers back, and see what generated confirmed bookings.',
                'canonical' => route('marketplace.for-owners'),
                'robots' => 'index,follow',
                'type' => 'website',
            ],
            'structuredData' => [$this->webPageSchema(
                'FinACourt for court owners',
                'Help nearby players discover your venue, book open court times, return for another game, and understand what generated confirmed bookings.',
                route('marketplace.for-owners'),
            )],
        ]);
    }

    public function pricing(PlatformServiceFeeCalculator $serviceFees): View
    {
        return view('marketplace.pricing', [
            'pricing' => $this->ownerPricing($serviceFees),
            'seo' => [
                'title' => 'Pricing for court owners',
                'description' => 'See how FinACourt separates the owner-set court price, player service fee, player total, and online court earnings without a monthly owner subscription.',
                'canonical' => route('marketplace.pricing'),
                'robots' => 'index,follow',
                'type' => 'website',
            ],
            'structuredData' => [$this->webPageSchema(
                'FinACourt owner pricing',
                'Current transaction-based pricing and payment boundaries for FinACourt court owners.',
                route('marketplace.pricing'),
            )],
        ]);
    }

    /** @return array{published_venues: int, active_courts: int, active_cities: int} */
    private function publicSupply(): array
    {
        return [
            'published_venues' => Venue::query()->marketplace()->count(),
            'active_courts' => CourtResource::query()
                ->marketplace()
                ->whereHas('venue', fn (Builder $query) => $query->marketplace())
                ->count(),
            'active_cities' => Venue::query()
                ->marketplace()
                ->distinct('city_slug')
                ->count('city_slug'),
        ];
    }

    /**
     * @return array{
     *   service_fee_active: bool,
     *   service_fee_summary: string|null,
     *   service_fee_minimum: string|null,
     *   service_fee_maximum: string|null,
     *   features: array<int, string>,
     *   sales_email: string
     * }
     */
    private function ownerPricing(PlatformServiceFeeCalculator $serviceFees): array
    {
        $rule = $serviceFees->activeRule('PHP');

        return [
            'service_fee_active' => $rule instanceof PlatformServiceFeeRule,
            'service_fee_summary' => $rule?->summary(),
            'service_fee_minimum' => $this->optionalMoney($rule?->minimum_fee_amount),
            'service_fee_maximum' => $this->optionalMoney($rule?->maximum_fee_amount),
            'features' => array_values(config('owner_pricing.features', [])),
            'sales_email' => (string) config('owner_pricing.sales_email', 'hello@example.com'),
        ];
    }

    private function optionalMoney(mixed $amount): ?string
    {
        if ($amount === null || (float) $amount <= 0) {
            return null;
        }

        return '₱'.number_format((float) $amount, 2);
    }

    /** @return array<string, string> */
    private function webPageSchema(string $name, string $description, string $url): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'WebPage',
            'name' => $name,
            'description' => $description,
            'url' => $url,
        ];
    }
}
