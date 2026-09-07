<?php

namespace Tests\Feature;

use App\Enums\PlatformServiceFeeType;
use App\Models\CourtResource;
use App\Models\Organization;
use App\Models\PlatformServiceFeeRule;
use App\Models\Sport;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OwnerAcquisitionLandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_can_browse_crawlable_owner_and_pricing_pages(): void
    {
        $ownerPage = $this->get(route('marketplace.for-owners'));

        $ownerPage->assertOk()
            ->assertHeader('X-PWA-Cache', 'public-short')
            ->assertSee('<h1', false)
            ->assertSee('<title>Court Booking Software for Sports Venue Owners Philippines | FinACourt</title>', false)
            ->assertSee('Court Booking Software That Helps You')
            ->assertSee('Get More Players')
            ->assertSee('FinACourt is a sports court booking, marketplace, and growth platform built for venue owners in the Philippines.')
            ->assertSee(route('marketplace.for-owners'), false)
            ->assertSee(route('marketplace.pricing'), false)
            ->assertSee(route('marketplace.courts.index'), false)
            ->assertSee(route('marketplace.directory.index'), false)
            ->assertSee(route('register'), false)
            ->assertSee('href="#how-it-works"', false)
            ->assertSee('index,follow')
            ->assertSee('More than a booking calendar')
            ->assertSee('Most court software helps manage bookings. FinACourt also helps create more of them.')
            ->assertSee('Get discovered by players looking for a court')
            ->assertSee('See what players are looking for')
            ->assertSee('Turn open hours into bookable deals')
            ->assertSee('Know where confirmed bookings came from')
            ->assertSee('Bring past players back')
            ->assertSee('Turn Google searches into booking opportunities')
            ->assertSee('Everything you need to manage court bookings')
            ->assertSee('Built for sports venues in the Philippines')
            ->assertSee('Add another way for players to find you')
            ->assertSee('You do not have to set up everything alone')
            ->assertSee('Your venue. Your prices. Your decisions.')
            ->assertSee('Frequently asked questions')
            ->assertSee('What is court booking software?')
            ->assertSee('FinACourt does not create, edit, verify, publish, or rank your Google listing.')
            ->assertSee('FinACourt does not currently synchronize another provider’s calendar automatically.')
            ->assertSee('Product preview — your account shows real venue activity, not sample results.')
            ->assertSee('Shown with a demo owner account.')
            ->assertSeeInOrder([
                'More than a booking calendar',
                'Get discovered by players looking for a court',
                'Understand player demand',
                'See demand. Fill open hours. Bring players back.',
                'Fill court hours that would otherwise stay empty',
                'Turn a past visit into another game',
                'See which paths led to confirmed bookings',
                'Turn Google searches into booking opportunities',
                'Everything you need to manage court bookings',
                'Built for sports venues in the Philippines',
                'Add another way for players to find you',
                'Frequently asked questions',
            ])
            ->assertDontSee('47 searches')
            ->assertDontSee('27 bookings')
            ->assertDontSee('₱9,400')
            ->assertDontSee('immutable price snapshots')
            ->assertDontSee('server-authoritative')
            ->assertDontSee('qualifying state')
            ->assertDontSee('acquisition attribution')
            ->assertDontSee('conversion')
            ->assertDontSee('cohort')
            ->assertDontSee('guaranteed more bookings')
            ->assertDontSee('guaranteed Google rankings')
            ->assertDontSee('best court booking software')
            ->assertSee('application/ld+json', false);

        $this->get(route('marketplace.pricing'))
            ->assertOk()
            ->assertHeader('X-PWA-Cache', 'public-short')
            ->assertSee('Simple pricing built around player bookings')
            ->assertSee('No active FinACourt service fee')
            ->assertSee('No monthly subscription')
            ->assertSee('PayMongo hosted checkout')
            ->assertSee(route('register'), false)
            ->assertDontSee('Founding venue pilot')
            ->assertDontSee('success redirect');

        $ownerPage
            ->assertSee('Transaction-based pricing')
            ->assertSee('No active service fee')
            ->assertSee('See how pricing works')
            ->assertDontSee('Founding venue pilot');
    }

    public function test_owner_page_has_complete_search_and_social_metadata_with_valid_visible_schema(): void
    {
        $response = $this->get(route('marketplace.for-owners'))->assertOk();
        $content = $response->getContent();
        $description = 'FinACourt is court booking software for Philippine sports venues. Manage reservations, get discovered, fill empty court hours, and track booking sources.';
        $image = asset('assets/demand-intelligence.png');

        $response
            ->assertSee('<meta name="description" content="'.$description.'">', false)
            ->assertSee('<link rel="canonical" href="'.route('marketplace.for-owners').'">', false)
            ->assertSee('<meta name="robots" content="index,follow">', false)
            ->assertSee('<meta property="og:title" content="Court Booking Software for Sports Venue Owners | FinACourt">', false)
            ->assertSee('<meta property="og:description" content="'.$description.'">', false)
            ->assertSee('<meta property="og:image" content="'.$image.'">', false)
            ->assertSee('<meta name="twitter:card" content="summary_large_image">', false)
            ->assertSee('<meta name="twitter:title" content="Court Booking Software for Sports Venue Owners | FinACourt">', false)
            ->assertSee('<meta name="twitter:description" content="'.$description.'">', false)
            ->assertSee('<meta name="twitter:image" content="'.$image.'">', false);

        $this->assertSame(1, substr_count($content, '<h1'));
        $this->assertSame(9, substr_count($content, '<details class="p-5'));
        $this->assertSame(9, substr_count($content, 'data-details-question'));
        $this->assertSame(9, substr_count($content, 'data-details-icon'));
        $this->assertDoesNotMatchRegularExpression('/<meta[^>]+noindex/i', $content);

        $styles = file_get_contents(resource_path('css/app.css'));
        $this->assertStringContainsString('summary [data-details-icon]', $styles);
        $this->assertStringNotContainsString('details[open] summary span {', $styles);

        preg_match_all(
            '/<script[^>]+type="application\/ld\+json"[^>]*>(.*?)<\/script>/s',
            $content,
            $matches,
        );

        $schemas = collect($matches[1])->map(function (string $json): array {
            $schema = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
            $this->assertIsArray($schema);

            return $schema;
        });

        $this->assertEqualsCanonicalizing(
            ['WebPage', 'SoftwareApplication', 'BreadcrumbList', 'FAQPage'],
            $schemas->pluck('@type')->all(),
        );

        $software = $schemas->firstWhere('@type', 'SoftwareApplication');
        $this->assertSame('BusinessApplication', $software['applicationCategory']);
        $this->assertSame('Web', $software['operatingSystem']);
        $this->assertArrayNotHasKey('aggregateRating', $software);
        $this->assertArrayNotHasKey('offers', $software);

        $faq = $schemas->firstWhere('@type', 'FAQPage');
        $this->assertCount(9, $faq['mainEntity']);
        $this->assertSame('What is court booking software?', $faq['mainEntity'][0]['name']);
        $this->assertStringContainsString(
            'FinACourt adds marketplace discovery',
            $faq['mainEntity'][0]['acceptedAnswer']['text'],
        );
    }

    public function test_owner_landing_destinations_remain_available(): void
    {
        $this->get(route('register'))->assertOk();
        $this->get(route('marketplace.directory.index'))->assertOk();
        $this->get(route('marketplace.courts.index'))->assertOk();
        $this->get(route('marketplace.pricing'))->assertOk();
    }

    public function test_owner_page_uses_selected_product_screenshots_as_accessible_supporting_evidence(): void
    {
        $screenshots = [
            [
                'path' => '/assets/demand-intelligence.png',
                'alt' => 'FinACourt owner page showing nearby player searches and the path from visits to confirmed bookings',
                'width' => 1892,
                'height' => 855,
            ],
            [
                'path' => '/assets/empty-slot-recommendations.png',
                'alt' => 'FinACourt owner suggestions showing open court times and an action to create a deal',
                'width' => 1901,
                'height' => 861,
            ],
            [
                'path' => '/assets/customer-reactivation.png',
                'alt' => 'FinACourt owner page for messaging eligible past players and tracking return bookings',
                'width' => 1902,
                'height' => 861,
            ],
            [
                'path' => '/assets/attribution-dashboard.png',
                'alt' => 'FinACourt owner report showing which sources and deals led to confirmed bookings',
                'width' => 1901,
                'height' => 865,
            ],
            [
                'path' => '/assets/google-visibility.png',
                'alt' => 'FinACourt Google visibility checklist and optional venue connection panel',
                'width' => 1900,
                'height' => 865,
            ],
        ];

        $response = $this->get(route('marketplace.for-owners'))->assertOk();
        $content = $response->getContent();

        foreach ($screenshots as $screenshot) {
            $this->assertFileExists(public_path(ltrim($screenshot['path'], '/')));
            $response
                ->assertSee('src="'.$screenshot['path'].'"', false)
                ->assertSee('href="'.$screenshot['path'].'"', false)
                ->assertSee('alt="'.$screenshot['alt'].'"', false)
                ->assertSee('width="'.$screenshot['width'].'"', false)
                ->assertSee('height="'.$screenshot['height'].'"', false);
        }

        $response
            ->assertSeeInOrder(array_column($screenshots, 'path'))
            ->assertSee('loading="lazy"', false)
            ->assertSee('decoding="async"', false)
            ->assertSee('View larger ↗')
            ->assertDontSee('/assets/empty-slot-promotions.png', false);

        $this->assertSame(5, substr_count($content, 'data-product-screenshot='));
        $this->assertSame(5, substr_count($content, 'loading="lazy"'));
    }

    public function test_owner_page_counts_only_real_public_inventory(): void
    {
        $sport = Sport::factory()->create(['is_active' => true]);
        $publicVenue = Venue::factory()->for(Organization::factory())->published()->create([
            'city' => 'Makati',
            'city_slug' => 'makati',
        ]);
        CourtResource::factory()->for($publicVenue)->for($sport)->count(2)->create(['is_active' => true]);

        $privateVenue = Venue::factory()->for(Organization::factory())->create([
            'city' => 'Pasig',
            'city_slug' => 'pasig',
        ]);
        CourtResource::factory()->for($privateVenue)->for($sport)->create(['is_active' => true]);

        $inactiveVenue = Venue::factory()->for(Organization::factory())->published()->create([
            'city' => 'Taguig',
            'city_slug' => 'taguig',
        ]);
        CourtResource::factory()->for($inactiveVenue)->for($sport)->create(['is_active' => false]);

        $this->get(route('marketplace.for-owners'))
            ->assertOk()
            ->assertSee('data-public-inventory="published-venues" data-public-count="1"', false)
            ->assertSee('data-public-inventory="active-courts" data-public-count="2"', false)
            ->assertSee('data-public-inventory="active-cities" data-public-count="1"', false);
    }

    public function test_public_pricing_uses_the_effective_checkout_fee_rule_and_exact_units(): void
    {
        PlatformServiceFeeRule::factory()->create([
            'name' => 'Old hidden fee',
            'fee_type' => PlatformServiceFeeType::Fixed,
            'fixed_amount' => '25.00',
            'is_active' => false,
            'deactivated_at' => now()->subDay(),
        ]);

        PlatformServiceFeeRule::factory()->create([
            'name' => 'Internal September rule',
            'fee_type' => PlatformServiceFeeType::Percentage,
            'percentage_basis_points' => 350,
            'fixed_amount' => null,
            'minimum_fee_amount' => '10.00',
            'maximum_fee_amount' => '99.00',
            'is_active' => true,
            'starts_at' => now()->subMinute(),
            'ends_at' => null,
            'deactivated_at' => null,
        ]);

        $this->get(route('marketplace.pricing'))
            ->assertOk()
            ->assertSee('3.50% of court price')
            ->assertSee('₱10.00')
            ->assertSee('₱99.00')
            ->assertDontSee('Old hidden fee')
            ->assertDontSee('Internal September rule');

        $this->get(route('marketplace.for-owners'))
            ->assertOk()
            ->assertSee('3.50% of court price')
            ->assertDontSee('Old hidden fee');
    }

    public function test_owner_acquisition_pages_are_in_the_public_sitemap(): void
    {
        $this->get(route('marketplace.sitemap'))
            ->assertOk()
            ->assertSee(route('marketplace.for-owners'), false)
            ->assertSee(route('marketplace.pricing'), false);
    }
}
