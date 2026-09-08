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
        $canonical = route('marketplace.for-owners');
        $description = 'FinACourt is court booking software for Philippine sports venues. Manage reservations, get discovered, fill empty court hours, and track booking sources.';
        $faq = $this->ownerFaq();

        return view('marketplace.owners', [
            'supply' => $this->publicSupply(),
            'pricing' => $this->ownerPricing($serviceFees),
            'faq' => $faq,
            'seo' => [
                'document_title' => 'Court Booking Software for Sports Venue Owners Philippines | FinACourt',
                'title' => 'Court Booking Software for Sports Venue Owners | FinACourt',
                'description' => $description,
                'canonical' => $canonical,
                'robots' => 'index,follow',
                'type' => 'website',
                'image' => asset('assets/demand-intelligence.png'),
                'image_alt' => 'FinACourt court booking software showing nearby player demand for a sports venue',
                'image_width' => 1892,
                'image_height' => 855,
            ],
            'structuredData' => [
                $this->webPageSchema(
                    'Court booking software for sports venue owners',
                    $description,
                    $canonical,
                ),
                $this->softwareApplicationSchema($description, $canonical),
                $this->breadcrumbSchema($canonical),
                $this->faqSchema($faq),
            ],
        ]);
    }

    public function pricing(PlatformServiceFeeCalculator $serviceFees): View
    {
        return view('marketplace.pricing', [
            'pricing' => $this->ownerPricing($serviceFees),
            'seo' => [
                'title' => 'Pricing for court owners',
                'description' => 'See how FinACourt separates the owner-set court price, online player service fee, player total, and online court earnings without a monthly owner subscription.',
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

    /** @return array<int, array{question: string, answer: string}> */
    private function ownerFaq(): array
    {
        return [
            [
                'question' => 'What is court booking software?',
                'answer' => 'Court booking software gives sports venues one place to publish court availability, receive reservations, manage schedules and prices, and keep booking records. FinACourt adds marketplace discovery and owner growth tools to those day-to-day features.',
            ],
            [
                'question' => 'Can players book courts directly online?',
                'answer' => 'Yes. Once a venue is approved, published, and has bookable courts, players can view live availability and reserve an open time. The payment choices shown depend on the payment methods currently available on FinACourt.',
            ],
            [
                'question' => 'Can I manage court schedules and pricing?',
                'answer' => 'Yes. Owners control their courts, opening hours, normal rates, availability, booking records, and published promotions from the owner workspace.',
            ],
            [
                'question' => 'Can FinACourt help my venue get more players?',
                'answer' => 'FinACourt can make a published venue discoverable in its marketplace, show grouped local search demand, help promote open court times, and help eligible past players return. It does not guarantee bookings or search rankings.',
            ],
            [
                'question' => 'Can I use FinACourt with another booking system?',
                'answer' => 'You can try FinACourt as an additional discovery and booking channel. FinACourt does not currently synchronize another provider’s calendar automatically, so you must keep availability aligned to avoid double bookings.',
            ],
            [
                'question' => 'Does FinACourt charge owners a monthly subscription?',
                'answer' => 'FinACourt does not currently charge a monthly owner subscription. Any active player service fee is shown separately before a player confirms an eligible online booking. Pay-at-venue bookings have no transaction or processing fee.',
            ],
            [
                'question' => 'Can FinACourt help with Google visibility?',
                'answer' => 'FinACourt provides a public venue and booking page plus a Google-readiness checklist. Where supported, an owner can connect an account to match an existing Google Business Profile. FinACourt does not promise rankings or create, verify, edit, or publish the Google profile.',
            ],
            [
                'question' => 'Which court sports does FinACourt support?',
                'answer' => 'The current catalog supports badminton, basketball, futsal, pickleball, tennis, and volleyball venues.',
            ],
            [
                'question' => 'How do I get started?',
                'answer' => 'Create a court-owner account to add a venue, or use the public venue guide to find and claim a pre-created listing. FinACourt reviews venues before they become publicly bookable.',
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function softwareApplicationSchema(string $description, string $url): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'SoftwareApplication',
            'name' => 'FinACourt',
            'applicationCategory' => 'BusinessApplication',
            'operatingSystem' => 'Web',
            'description' => $description,
            'url' => $url,
            'audience' => [
                '@type' => 'Audience',
                'audienceType' => 'Sports venue owners in the Philippines',
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function breadcrumbSchema(string $url): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => [
                [
                    '@type' => 'ListItem',
                    'position' => 1,
                    'name' => 'Home',
                    'item' => route('marketplace.home'),
                ],
                [
                    '@type' => 'ListItem',
                    'position' => 2,
                    'name' => 'For court owners',
                    'item' => $url,
                ],
            ],
        ];
    }

    /**
     * @param  array<int, array{question: string, answer: string}>  $faq
     * @return array<string, mixed>
     */
    private function faqSchema(array $faq): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => collect($faq)->map(fn (array $item) => [
                '@type' => 'Question',
                'name' => $item['question'],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $item['answer'],
                ],
            ])->all(),
        ];
    }
}
