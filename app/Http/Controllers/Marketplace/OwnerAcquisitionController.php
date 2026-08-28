<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\CourtResource;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;

class OwnerAcquisitionController extends Controller
{
    public function show(): View
    {
        return view('marketplace.owners', [
            'supply' => $this->publicSupply(),
            'pilotPlan' => $this->pilotPlan(),
            'seo' => [
                'title' => 'Get more court bookings with FinACourt',
                'description' => 'Put your courts online, let players book available times, offer simple deals, and see what helped bring each booking.',
                'canonical' => route('marketplace.for-owners'),
                'robots' => 'index,follow',
                'type' => 'website',
            ],
            'structuredData' => [$this->webPageSchema(
                'FinACourt for court owners',
                'Help players find your courts, book open times, come back again, and understand what brought each booking.',
                route('marketplace.for-owners'),
            )],
        ]);
    }

    public function pricing(): View
    {
        return view('marketplace.pricing', [
            'pilotPlan' => $this->pilotPlan(),
            'seo' => [
                'title' => 'Pricing for court owners',
                'description' => 'See the current FinACourt founding-venue pilot price, included owner tools, and transparent payment-fee boundaries.',
                'canonical' => route('marketplace.pricing'),
                'robots' => 'index,follow',
                'type' => 'website',
            ],
            'structuredData' => [$this->webPageSchema(
                'FinACourt owner pricing',
                'Current public pricing and included tools for the founding-venue pilot.',
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

    /** @return array{name: string, monthly_fee: string, booking_fee: string, availability: string, features: array<int, string>, sales_email: string} */
    private function pilotPlan(): array
    {
        $monthlyFeeCentavos = max(0, (int) config('owner_pricing.pilot.monthly_fee_centavos', 0));
        $bookingFeeBasisPoints = max(0, (int) config('owner_pricing.pilot.booking_fee_basis_points', 0));

        return [
            'name' => (string) config('owner_pricing.pilot.name', 'Founding venue pilot'),
            'monthly_fee' => $this->formatPesos($monthlyFeeCentavos),
            'booking_fee' => $this->formatPercentage($bookingFeeBasisPoints),
            'availability' => (string) config('owner_pricing.pilot.availability', 'Accepting pilot venues'),
            'features' => array_values(config('owner_pricing.pilot.features', [])),
            'sales_email' => (string) config('owner_pricing.sales_email', 'hello@example.com'),
        ];
    }

    private function formatPesos(int $centavos): string
    {
        $pesos = $centavos / 100;
        $decimals = $centavos % 100 === 0 ? 0 : 2;

        return '₱'.number_format($pesos, $decimals);
    }

    private function formatPercentage(int $basisPoints): string
    {
        $percentage = $basisPoints / 100;
        $decimals = $basisPoints % 100 === 0 ? 0 : ($basisPoints % 10 === 0 ? 1 : 2);

        return number_format($percentage, $decimals).'%';
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
