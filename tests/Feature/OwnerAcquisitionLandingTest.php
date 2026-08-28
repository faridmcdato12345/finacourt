<?php

namespace Tests\Feature;

use App\Models\CourtResource;
use App\Models\Organization;
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
            ->assertSee('Fill empty court times')
            ->assertSee(route('marketplace.for-owners'), false)
            ->assertSee(route('marketplace.pricing'), false)
            ->assertSee(route('register'), false)
            ->assertSee('index,follow')
            ->assertSee('See what players want')
            ->assertSee('Fill slow hours')
            ->assertSee('Know what brought the booking')
            ->assertSee('Invite past customers back')
            ->assertSee('Make your venue easier to find')
            ->assertSee('Get clear next steps')
            ->assertSee('Want help setting it up')
            ->assertSee('Example only')
            ->assertSee('Google profile connection is optional; QR links and map directions still work.')
            ->assertDontSee('immutable price snapshots')
            ->assertDontSee('server-authoritative')
            ->assertDontSee('qualifying state')
            ->assertDontSee('attribution')
            ->assertDontSee('conversion')
            ->assertDontSee('cohort')
            ->assertSee('application/ld+json', false);

        $this->get(route('marketplace.pricing'))
            ->assertOk()
            ->assertHeader('X-PWA-Cache', 'public-short')
            ->assertSee('Transparent pricing for the founding-venue pilot')
            ->assertSee('₱0')
            ->assertSee('0% platform booking fee during pilot')
            ->assertSee('Post-pilot pricing is not yet published')
            ->assertSee(route('register'), false)
            ->assertDontSee('success redirect');
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

    public function test_public_pricing_is_config_driven_and_uses_exact_units(): void
    {
        config()->set('owner_pricing.pilot.monthly_fee_centavos', 149900);
        config()->set('owner_pricing.pilot.booking_fee_basis_points', 350);

        $this->get(route('marketplace.pricing'))
            ->assertOk()
            ->assertSee('₱1,499')
            ->assertSee('3.5% platform booking fee during pilot');
    }

    public function test_owner_acquisition_pages_are_in_the_public_sitemap(): void
    {
        $this->get(route('marketplace.sitemap'))
            ->assertOk()
            ->assertSee(route('marketplace.for-owners'), false)
            ->assertSee(route('marketplace.pricing'), false);
    }
}
