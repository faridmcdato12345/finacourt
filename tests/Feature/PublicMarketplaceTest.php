<?php

namespace Tests\Feature;

use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\CourtResource;
use App\Models\OperatingHour;
use App\Models\Organization;
use App\Models\Promotion;
use App\Models\Sport;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenuePhoto;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicMarketplaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_can_browse_server_rendered_marketplace_pages(): void
    {
        [$venue] = $this->publicVenue();

        $this->get(route('marketplace.home'))
            ->assertOk()
            ->assertSee('<h1', false)
            ->assertSee('Find and book courts')
            ->assertSee('/icons/finacourt-logo-192.png', false)
            ->assertSee('Server-checked availability')
            ->assertSee('data-icon="location"', false)
            ->assertSee('data-icon="calendar"', false)
            ->assertSee('data-icon="sport-badminton"', false)
            ->assertSee('data-public-select', false)
            ->assertSee('data-public-select-config', false)
            ->assertSee('data-public-date', false)
            ->assertSee('data-public-date-config', false)
            ->assertSee('"variant":"hero-slim"', false)
            ->assertSee('data-popular-courts-carousel', false)
            ->assertSee('data-carousel-next', false)
            ->assertSee('data-icon="grid-dots"', false)
            ->assertSee('<select name="city"', false)
            ->assertSee('<input name="date" type="date"', false)
            ->assertSee($venue->name)
            ->assertDontSee('data-page=', false);

        $this->get(route('marketplace.courts.index'))
            ->assertOk()
            ->assertSee('Find a court that fits your game')
            ->assertSee('data-scrollable-filters', false)
            ->assertSee('data-filter-scroll-region', false)
            ->assertSee('data-public-number', false)
            ->assertSee('data-public-number-config', false)
            ->assertSee('<input id="maximum-hourly-price" type="number" name="max_price"', false)
            ->assertSee($venue->name);

        $this->get(route('marketplace.venues.show', $venue->slug))
            ->assertOk()
            ->assertSee($venue->name)
            ->assertSee('data-venue-gallery', false)
            ->assertSee('About this venue')
            ->assertSee('Venue details', false)
            ->assertSee('data-share-page', false)
            ->assertSee('data-live-availability', false)
            ->assertSee('"submitOnChange":true', false)
            ->assertSee('Live schedule')
            ->assertSee('Check availability');
    }

    public function test_homepage_social_proof_uses_distinct_confirmed_players_only(): void
    {
        [$venue, $resource] = $this->publicVenue();
        $confirmedPlayer = User::factory()->create(['name' => 'Aly Santos']);
        $cancelledPlayer = User::factory()->create(['name' => 'Ignored Player']);

        Booking::factory()->for($resource, 'resource')->count(2)->create([
            'status' => BookingStatus::Confirmed,
            'source' => BookingSource::Marketplace,
            'player_user_id' => $confirmedPlayer->getKey(),
        ]);
        Booking::factory()->for($resource, 'resource')->cancelled()->create([
            'source' => BookingSource::Marketplace,
            'player_user_id' => $cancelledPlayer->getKey(),
        ]);

        $this->get(route('marketplace.home'))
            ->assertOk()
            ->assertSee('data-player-social-proof', false)
            ->assertSee('data-player-initial', false)
            ->assertSee('Join 1 players')
            ->assertSee('booking on')
            ->assertSee('AS')
            ->assertDontSee('Join 2 players');

        $this->assertSame($venue->getKey(), $resource->venue_id);
    }

    public function test_homepage_social_proof_excludes_non_marketplace_and_non_public_bookings(): void
    {
        [, $publicResource] = $this->publicVenue();
        [, $privateResource] = $this->publicVenue([
            'slug' => 'private-social-proof',
            'is_published' => false,
        ]);
        [, $inactiveResource] = $this->publicVenue([
            'slug' => 'inactive-social-proof',
        ], ['is_active' => false]);
        $eligible = User::factory()->create(['name' => 'Eligible Player']);
        $manual = User::factory()->create(['name' => 'Manual Customer']);
        $private = User::factory()->create(['name' => 'Private Player']);
        $inactive = User::factory()->create(['name' => 'Inactive Player']);

        Booking::factory()->for($publicResource, 'resource')->create([
            'source' => BookingSource::Marketplace,
            'player_user_id' => $eligible->getKey(),
        ]);
        Booking::factory()->for($publicResource, 'resource')->create([
            'source' => BookingSource::Manual,
            'player_user_id' => $manual->getKey(),
        ]);
        Booking::factory()->for($privateResource, 'resource')->create([
            'source' => BookingSource::Marketplace,
            'player_user_id' => $private->getKey(),
        ]);
        Booking::factory()->for($inactiveResource, 'resource')->create([
            'source' => BookingSource::Marketplace,
            'player_user_id' => $inactive->getKey(),
        ]);

        $this->get(route('marketplace.home'))
            ->assertOk()
            ->assertSee('Join 1 players')
            ->assertSee('>EP</span>', false)
            ->assertDontSee('>MC</span>', false)
            ->assertDontSee('>PP</span>', false)
            ->assertDontSee('>IP</span>', false);
    }

    public function test_homepage_features_a_current_public_deal_outside_the_venue_carousel(): void
    {
        [$dealVenue, $resource] = $this->publicVenue([
            'name' => 'Deal Venue Outside Carousel',
            'slug' => 'deal-venue-outside-carousel',
            'verified_at' => null,
        ]);
        $promotion = Promotion::factory()->for($dealVenue)->create([
            'organization_id' => $dealVenue->organization_id,
            'resource_id' => $resource->getKey(),
            'title' => 'Homepage court special',
            'discount_value' => '25.00',
        ]);
        $coverPhoto = VenuePhoto::factory()->for($dealVenue)->create([
            'storage_path' => 'venues/deal-venue/featured-cover.jpg',
            'is_primary' => true,
        ]);

        foreach (range(1, 6) as $index) {
            $this->publicVenue([
                'name' => "Featured Venue {$index}",
                'slug' => "featured-venue-{$index}",
                'verified_at' => now(),
            ]);
        }

        $this->get(route('marketplace.home'))
            ->assertOk()
            ->assertSee('data-featured-deal', false)
            ->assertSee('data-featured-deal-cover', false)
            ->assertSee('/storage/'.$coverPhoto->storage_path, false)
            ->assertSee($promotion->title)
            ->assertSee('25% off')
            ->assertSee('campaign='.$promotion->campaign_token, false);
    }

    public function test_homepage_featured_deal_keeps_its_placeholder_when_the_venue_has_no_photo(): void
    {
        [$venue, $resource] = $this->publicVenue();
        Promotion::factory()->for($venue)->create([
            'organization_id' => $venue->organization_id,
            'resource_id' => $resource->getKey(),
            'title' => 'Photo pending special',
        ]);

        $this->get(route('marketplace.home'))
            ->assertOk()
            ->assertSee('data-featured-deal', false)
            ->assertDontSee('data-featured-deal-cover', false)
            ->assertSee('Photo pending special');
    }

    public function test_public_venue_page_has_canonical_metadata_and_accurate_structured_data(): void
    {
        [$venue, $resource] = $this->publicVenue([
            'name' => 'Riverside Racquet Club',
            'slug' => 'riverside-racquet-club',
            'description' => 'Six well maintained courts beside the river.',
            'address' => '18 River Road',
            'phone' => '+63 917 555 0188',
        ], ['base_hourly_rate' => '725.00']);

        $canonical = route('marketplace.venues.show', $venue->slug);
        $response = $this->get($canonical);

        $response->assertOk()
            ->assertSee('<title>Riverside Racquet Club courts in Makati · FinACourt</title>', false)
            ->assertSee('<link rel="canonical" href="'.$canonical.'">', false)
            ->assertSee('<meta property="og:url" content="'.$canonical.'">', false)
            ->assertSee('"@type":"SportsActivityLocation"', false)
            ->assertSee('"streetAddress":"18 River Road"', false)
            ->assertSee('"price":"725.00"', false)
            ->assertSee('"priceCurrency":"PHP"', false)
            ->assertDontSee('https://schema.org/InStock', false)
            ->assertDontSee('aggregateRating')
            ->assertDontSee('reviewCount');

        $this->assertSame('725.00', $resource->base_hourly_rate);
    }

    public function test_unpublished_or_inventory_thin_venues_are_not_public(): void
    {
        [$privateVenue] = $this->publicVenue([
            'name' => 'Private Training Hall',
            'slug' => 'private-training-hall',
            'is_published' => false,
        ]);
        [$thinVenue] = $this->publicVenue([
            'name' => 'Closed Court Center',
            'slug' => 'closed-court-center',
        ], ['is_active' => false]);

        $this->get(route('marketplace.venues.show', $privateVenue->slug))->assertNotFound();
        $this->get(route('marketplace.venues.show', $thinVenue->slug))->assertNotFound();

        $this->get(route('marketplace.courts.index'))
            ->assertOk()
            ->assertDontSee($privateVenue->name)
            ->assertDontSee($thinVenue->name);
    }

    public function test_city_and_sport_landing_pages_filter_real_inventory(): void
    {
        [$makatiVenue, , $badminton] = $this->publicVenue([
            'name' => 'Makati Smash Club',
            'slug' => 'makati-smash-club',
        ]);
        [$cebuVenue] = $this->publicVenue([
            'name' => 'Cebu Court House',
            'slug' => 'cebu-court-house',
            'city' => 'Cebu City',
            'city_slug' => 'cebu-city',
            'province' => 'Cebu',
            'province_slug' => 'cebu',
        ], [], ['name' => 'Tennis', 'slug' => 'tennis']);

        $this->get(route('marketplace.courts.city', 'makati'))
            ->assertOk()
            ->assertSee('Sports courts in Makati')
            ->assertSee($makatiVenue->name)
            ->assertDontSee($cebuVenue->name);

        $this->get(route('marketplace.courts.sport-city', [$badminton->slug, 'makati']))
            ->assertOk()
            ->assertSee('Badminton courts in Makati')
            ->assertSee($makatiVenue->name)
            ->assertDontSee($cebuVenue->name);

        $this->get(route('marketplace.courts.sport-city', ['tennis', 'makati']))
            ->assertNotFound();
        $this->get(route('marketplace.courts.city', 'inventory-free-city'))
            ->assertNotFound();
    }

    public function test_discovery_filters_and_filtered_pages_are_not_indexed(): void
    {
        [$indoorVenue] = $this->publicVenue([
            'name' => 'Indoor Match Point',
            'slug' => 'indoor-match-point',
        ], ['setting' => 'indoor', 'base_hourly_rate' => '500.00']);
        [$outdoorVenue] = $this->publicVenue([
            'name' => 'Outdoor Match Point',
            'slug' => 'outdoor-match-point',
        ], ['setting' => 'outdoor', 'base_hourly_rate' => '900.00']);

        $canonical = route('marketplace.courts.index');
        $this->get($canonical.'?setting=indoor&max_price=600')
            ->assertOk()
            ->assertSee($indoorVenue->name)
            ->assertDontSee($outdoorVenue->name)
            ->assertSee('<meta name="robots" content="noindex,follow">', false)
            ->assertSee('<link rel="canonical" href="'.$canonical.'">', false);

        $date = now('Asia/Manila')->addDay()->toDateString();
        $this->get($canonical.'?city=__court_select_empty__&sport=__court_select_empty__&date='.$date.'&start_time=13%3A00&duration_minutes=60')
            ->assertOk()
            ->assertSee('2 venues found')
            ->assertSee($indoorVenue->name)
            ->assertSee($outdoorVenue->name);

        $this->get($canonical.'?max_price=0')
            ->assertOk()
            ->assertSee('0 venues found')
            ->assertDontSee($indoorVenue->name)
            ->assertDontSee($outdoorVenue->name);
    }

    public function test_maximum_price_filter_uses_public_promotional_prices_and_slot_applicability(): void
    {
        [$unrestrictedVenue, $unrestrictedResource] = $this->publicVenue([
            'name' => 'Anytime Deal Courts',
            'slug' => 'anytime-deal-courts',
        ], ['base_hourly_rate' => '600.00']);
        $unrestrictedPromotion = Promotion::factory()->for($unrestrictedVenue)->create([
            'organization_id' => $unrestrictedVenue->organization_id,
            'resource_id' => $unrestrictedResource->getKey(),
            'title' => 'Anytime 25 percent deal',
            'discount_value' => '25.00',
        ]);

        [$scheduledVenue, $scheduledResource] = $this->publicVenue([
            'name' => 'Scheduled Deal Courts',
            'slug' => 'scheduled-deal-courts',
        ], ['base_hourly_rate' => '800.00']);
        $date = CarbonImmutable::now('Asia/Manila')->addDays(7);
        $scheduledPromotion = Promotion::factory()->for($scheduledVenue)->create([
            'organization_id' => $scheduledVenue->organization_id,
            'resource_id' => $scheduledResource->getKey(),
            'title' => 'Midday half price deal',
            'discount_value' => '50.00',
            'days_of_week' => [$date->dayOfWeek],
            'starts_at_time' => '12:00',
            'ends_at_time' => '14:00',
        ]);

        [$inactiveVenue, $inactiveResource] = $this->publicVenue([
            'name' => 'Inactive Deal Courts',
            'slug' => 'inactive-deal-courts',
        ], ['base_hourly_rate' => '600.00']);
        Promotion::factory()->inactive()->for($inactiveVenue)->create([
            'organization_id' => $inactiveVenue->organization_id,
            'resource_id' => $inactiveResource->getKey(),
            'discount_value' => '50.00',
        ]);

        $this->get(route('marketplace.courts.index', ['max_price' => 500]))
            ->assertOk()
            ->assertSee($unrestrictedVenue->name)
            ->assertSee($scheduledVenue->name)
            ->assertDontSee($inactiveVenue->name)
            ->assertSee('data-effective-hourly-price="450.00"', false)
            ->assertSee('data-effective-hourly-price="400.00"', false)
            ->assertSee('campaign='.$unrestrictedPromotion->campaign_token, false)
            ->assertSee('campaign='.$scheduledPromotion->campaign_token, false);

        $matchingSlot = [
            'max_price' => 500,
            'date' => $date->toDateString(),
            'start_time' => '13:00',
            'duration_minutes' => 60,
        ];
        $this->get(route('marketplace.courts.index', $matchingSlot))
            ->assertOk()
            ->assertSee($unrestrictedVenue->name)
            ->assertSee($scheduledVenue->name);

        $this->get(route('marketplace.courts.index', [
            ...$matchingSlot,
            'start_time' => '14:00',
        ]))
            ->assertOk()
            ->assertSee($unrestrictedVenue->name)
            ->assertDontSee($scheduledVenue->name)
            ->assertDontSee($scheduledPromotion->title);
    }

    public function test_venue_availability_preview_uses_active_booking_conflicts(): void
    {
        [$venue, $resource] = $this->publicVenue();
        $date = now('Asia/Manila')->addDays(7)->toDateString();
        $start = CarbonImmutable::createFromFormat('!Y-m-d H:i', $date.' 09:00', 'Asia/Manila');

        Booking::factory()->for($resource, 'resource')->create([
            'status' => BookingStatus::Confirmed,
            'start_at' => $start->utc(),
            'end_at' => $start->addHour()->utc(),
            'created_by_user_id' => User::factory(),
        ]);

        $this->get(route('marketplace.venues.show', [
            'venueSlug' => $venue->slug,
            'resource' => $resource->getKey(),
            'date' => $date,
            'duration' => 60,
        ]))
            ->assertOk()
            ->assertSee('08:00–09:00')
            ->assertSee('09:00–10:00')
            ->assertSee('line-through', false)
            ->assertSee('<meta name="robots" content="noindex,follow">', false);
    }

    public function test_venue_availability_conflicts_are_isolated_to_the_booked_court(): void
    {
        [$venue, $firstResource] = $this->publicVenue();
        $secondResource = CourtResource::factory()->for($venue)->create([
            'sport_id' => $firstResource->sport_id,
            'name' => 'Court Two',
            'booking_increment_minutes' => 60,
        ]);
        $date = CarbonImmutable::now('Asia/Manila')->addDays(7);
        $start = $date->setTime(17, 0);

        Booking::factory()->for($secondResource, 'resource')->create([
            'status' => BookingStatus::Confirmed,
            'start_at' => $start->utc(),
            'end_at' => $start->addHours(2)->utc(),
            'created_by_user_id' => User::factory(),
        ]);

        $this->get(route('marketplace.venues.show', [
            'venueSlug' => $venue->slug,
            'resource' => $firstResource->getKey(),
            'date' => $date->toDateString(),
        ]))
            ->assertOk()
            ->assertSee('Select your times on '.$firstResource->name)
            ->assertSee('aria-label="Select 17:00 to 18:00"', false)
            ->assertSee('aria-label="Select 18:00 to 19:00"', false)
            ->assertDontSee('data-unavailable-slot data-start="17:00"', false)
            ->assertDontSee('data-unavailable-slot data-start="18:00"', false);

        $this->get(route('marketplace.venues.show', [
            'venueSlug' => $venue->slug,
            'resource' => $secondResource->getKey(),
            'date' => $date->toDateString(),
        ]))
            ->assertOk()
            ->assertSee('Select your times on '.$secondResource->name)
            ->assertSee('data-unavailable-slot data-start="17:00"', false)
            ->assertSee('data-unavailable-slot data-start="18:00"', false)
            ->assertDontSee('aria-label="Select 17:00 to 18:00"', false)
            ->assertDontSee('aria-label="Select 18:00 to 19:00"', false);
    }

    public function test_sitemap_only_contains_meaningful_public_inventory(): void
    {
        [$publicVenue, , $sport] = $this->publicVenue();
        [$privateVenue] = $this->publicVenue([
            'name' => 'Hidden Courts',
            'slug' => 'hidden-courts',
            'is_published' => false,
            'city' => 'Pasig',
            'city_slug' => 'pasig',
        ]);
        [$inactiveVenue] = $this->publicVenue([
            'name' => 'Inactive Courts',
            'slug' => 'inactive-courts',
            'city' => 'Taguig',
            'city_slug' => 'taguig',
        ], ['is_active' => false]);

        $response = $this->get(route('marketplace.sitemap'));

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee(route('marketplace.venues.show', $publicVenue->slug), false)
            ->assertSee(route('marketplace.courts.city', 'makati'), false)
            ->assertSee(route('marketplace.courts.sport-city', [$sport->slug, 'makati']), false)
            ->assertDontSee(route('marketplace.venues.show', $privateVenue->slug), false)
            ->assertDontSee(route('marketplace.venues.show', $inactiveVenue->slug), false)
            ->assertDontSee('/courts/pasig', false)
            ->assertDontSee('/courts/taguig', false);
    }

    public function test_robots_file_points_to_the_dynamic_sitemap(): void
    {
        $this->get(route('marketplace.robots'))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee('Disallow: /owner/')
            ->assertSee('Disallow: /platform/')
            ->assertSee('Sitemap: '.route('marketplace.sitemap'));
    }

    /**
     * @param  array<string, mixed>  $venueAttributes
     * @param  array<string, mixed>  $resourceAttributes
     * @param  array<string, mixed>  $sportAttributes
     * @return array{Venue, CourtResource, Sport}
     */
    private function publicVenue(
        array $venueAttributes = [],
        array $resourceAttributes = [],
        array $sportAttributes = [],
    ): array {
        $organization = Organization::factory()->create(['timezone' => 'Asia/Manila']);
        $venue = Venue::factory()->for($organization)->published()->create([
            'city' => 'Makati',
            'city_slug' => 'makati',
            'province' => 'Metro Manila',
            'province_slug' => 'metro-manila',
            ...$venueAttributes,
        ]);
        $sportData = [
            'name' => 'Badminton',
            'slug' => 'badminton',
            'is_active' => true,
            ...$sportAttributes,
        ];
        $sport = Sport::query()->firstOrCreate(
            ['slug' => $sportData['slug']],
            $sportData,
        );
        $resource = CourtResource::factory()->for($venue)->for($sport)->create([
            'name' => 'Court One',
            'setting' => 'indoor',
            'base_hourly_rate' => '650.00',
            'booking_increment_minutes' => 60,
            ...$resourceAttributes,
        ]);

        foreach (range(0, 6) as $day) {
            OperatingHour::factory()->for($venue)->create([
                'day_of_week' => $day,
                'opens_at' => '08:00',
                'closes_at' => '22:00',
            ]);
        }

        return [$venue, $resource, $sport];
    }
}
