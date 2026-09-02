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
            ->assertSee('<title>Get more players and court bookings with FinACourt · FinACourt</title>', false)
            ->assertSee('Get discovered. Fill more court hours.')
            ->assertSee('Keep players coming back.')
            ->assertSee(route('marketplace.for-owners'), false)
            ->assertSee(route('marketplace.pricing'), false)
            ->assertSee(route('register'), false)
            ->assertSee('href="#how-it-works"', false)
            ->assertSee('index,follow')
            ->assertSee('Get discovered by players looking for a court')
            ->assertSee('See what players are looking for')
            ->assertSee('Turn open hours into bookable deals')
            ->assertSee('Know where confirmed bookings came from')
            ->assertSee('Bring past players back')
            ->assertSee('Turn Google interest into a booking opportunity')
            ->assertSee('You do not have to set up everything alone')
            ->assertSee('Your venue. Your prices. Your decisions.')
            ->assertSee('FinACourt does not create, edit, verify, publish, or rank your Google listing.')
            ->assertSee('Product preview — your account shows real venue activity, not sample results.')
            ->assertSee('Shown with a demo owner account.')
            ->assertSeeInOrder([
                'Get discovered by players looking for a court',
                'Understand player demand',
                'See demand. Fill open hours. Bring players back.',
                'Fill court hours that would otherwise stay empty',
                'Turn a past visit into another game',
                'See which paths led to confirmed bookings',
                'Turn Google interest into a booking opportunity',
                'Handle the booking after you win it',
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
