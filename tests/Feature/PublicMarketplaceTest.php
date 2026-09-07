<?php

namespace Tests\Feature;

use App\Enums\AnalyticsEventType;
use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Models\AnalyticsEvent;
use App\Models\Booking;
use App\Models\CourtAvailabilityBlock;
use App\Models\CourtResource;
use App\Models\OperatingHour;
use App\Models\Organization;
use App\Models\Promotion;
use App\Models\PsgcLocation;
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
            ->assertSee('id="google-data-use"', false)
            ->assertSee('When you choose to connect Google')
            ->assertSee('Google is optional on FinACourt')
            ->assertSee('Google access tokens are not kept for ordinary sign-in')
            ->assertSee('This version does not create, edit, verify, or publish a Google profile')
            ->assertSee('href="/privacy#google-data"', false)
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
            ->assertSee('Sports Courts in Makati')
            ->assertSee($makatiVenue->name)
            ->assertDontSee($cebuVenue->name);

        $this->get(route('marketplace.courts.sport-city', [$badminton->slug, 'makati']))
            ->assertOk()
            ->assertSee('Badminton Courts in Makati')
            ->assertSee($makatiVenue->name)
            ->assertDontSee($cebuVenue->name);

        $this->get(route('marketplace.courts.sport-city', ['tennis', 'makati']))
            ->assertNotFound();
        $this->get(route('marketplace.courts.city', 'inventory-free-city'))
            ->assertNotFound();
    }

    public function test_city_landing_uses_only_real_public_inventory_for_local_content(): void
    {
        [$firstVenue, , $pickleball] = $this->publicVenue([
            'name' => 'Bunawan Pickleball Center',
            'slug' => 'bunawan-pickleball-center',
            'city' => 'Bunawan',
            'city_slug' => 'bunawan',
            'province' => 'Agusan del Sur',
            'province_slug' => 'agusan-del-sur',
        ], [
            'setting' => 'indoor',
            'base_hourly_rate' => '100.00',
        ], [
            'name' => 'Pickleball',
            'slug' => 'pickleball',
        ]);
        $badminton = Sport::query()->firstOrCreate(
            ['slug' => 'badminton'],
            ['name' => 'Badminton', 'is_active' => true],
        );
        CourtResource::factory()->for($firstVenue)->for($badminton)->create([
            'name' => 'Badminton Court',
            'setting' => 'covered',
            'base_hourly_rate' => '225.00',
        ]);
        [$secondVenue] = $this->publicVenue([
            'name' => 'Bunawan Outdoor Courts',
            'slug' => 'bunawan-outdoor-courts',
            'city' => 'Bunawan',
            'city_slug' => 'bunawan',
            'province' => 'Agusan del Sur',
            'province_slug' => 'agusan-del-sur',
        ], [
            'setting' => 'outdoor',
            'base_hourly_rate' => '150.00',
        ], [
            'name' => 'Pickleball',
            'slug' => 'pickleball',
        ]);
        [$hiddenVenue] = $this->publicVenue([
            'name' => 'Hidden Bunawan Venue',
            'slug' => 'hidden-bunawan-venue',
            'city' => 'Bunawan',
            'city_slug' => 'bunawan',
            'province' => 'Agusan del Sur',
            'province_slug' => 'agusan-del-sur',
            'is_published' => false,
        ], [
            'base_hourly_rate' => '20.00',
        ], [
            'name' => 'Tennis',
            'slug' => 'tennis',
        ]);
        $basketball = Sport::query()->firstOrCreate(
            ['slug' => 'basketball'],
            ['name' => 'Basketball', 'is_active' => true],
        );
        CourtResource::factory()->inactive()->for($firstVenue)->for($basketball)->create([
            'name' => 'Hidden Basketball Court',
            'base_hourly_rate' => '10.00',
        ]);
        [$relatedVenue] = $this->publicVenue([
            'name' => 'Prosperidad Sports Hub',
            'slug' => 'prosperidad-sports-hub',
            'city' => 'Prosperidad',
            'city_slug' => 'prosperidad',
            'province' => 'Agusan del Sur',
            'province_slug' => 'agusan-del-sur',
        ]);
        $canonical = route('marketplace.courts.city', 'bunawan');

        $this->get($canonical)
            ->assertOk()
            ->assertSee('<link rel="canonical" href="'.$canonical.'">', false)
            ->assertSee('Sports Courts in Bunawan')
            ->assertSee('Find sports courts in Bunawan, Agusan del Sur. Compare 2 venues')
            ->assertSee('data-location-summary', false)
            ->assertSee('data-location-stat="venues"', false)
            ->assertSee('2 venues')
            ->assertSee('3 courts')
            ->assertSee('Badminton &amp; Pickleball', false)
            ->assertSee('3 court settings')
            ->assertSee('data-minimum-hourly-price="100.00"', false)
            ->assertSee('From ₱100/hour')
            ->assertSee('Sports available in Bunawan')
            ->assertSee('Badminton Courts in Bunawan')
            ->assertSee('Pickleball Courts in Bunawan')
            ->assertSee('href="'.route('marketplace.courts.sport-city', [$badminton->slug, 'bunawan']).'"', false)
            ->assertSee('href="'.route('marketplace.courts.sport-city', [$pickleball->slug, 'bunawan']).'"', false)
            ->assertSee('1 venue · 1 court')
            ->assertSee('2 venues · 2 courts')
            ->assertSee('FinACourt currently lists 2 active sports venues in Bunawan, Agusan del Sur, with 3 bookable courts.')
            ->assertSee('Current local inventory includes badminton and pickleball, with rates starting from ₱100 per hour.')
            ->assertSee('Looking for a court near you in Bunawan?')
            ->assertSee('Indoor, outdoor and covered outdoor courts are currently listed in Bunawan.')
            ->assertSee('More courts in Agusan del Sur')
            ->assertSee('href="'.route('marketplace.courts.city', 'prosperidad').'"', false)
            ->assertSee('"@type":"ItemList"', false)
            ->assertSee('"numberOfItems":2', false)
            ->assertSee(route('marketplace.venues.show', $firstVenue->slug), false)
            ->assertSee(route('marketplace.venues.show', $secondVenue->slug), false)
            ->assertDontSee($hiddenVenue->name)
            ->assertDontSee('data-location-sport="tennis"', false)
            ->assertDontSee('data-location-sport="basketball"', false)
            ->assertDontSee('data-minimum-hourly-price="20.00"', false)
            ->assertDontSee(route('marketplace.venues.show', $hiddenVenue->slug), false)
            ->assertSee('<meta name="description" content="Find sports courts in Bunawan, Agusan del Sur. Compare 2 venues and 3 courts, including badminton and pickleball, from ₱100/hour on FinACourt.">', false);

        $this->assertNotSame($firstVenue->getKey(), $secondVenue->getKey());
        $this->assertNotSame($firstVenue->getKey(), $relatedVenue->getKey());
    }

    public function test_city_landing_omits_price_claims_when_no_positive_public_rate_exists(): void
    {
        $this->publicVenue([
            'name' => 'Free Community Court',
            'slug' => 'free-community-court',
            'city' => 'Bunawan',
            'city_slug' => 'bunawan',
            'province' => 'Agusan del Sur',
            'province_slug' => 'agusan-del-sur',
        ], [
            'base_hourly_rate' => '0.00',
        ], [
            'name' => 'Pickleball',
            'slug' => 'pickleball',
        ]);

        $this->get(route('marketplace.courts.city', 'bunawan'))
            ->assertOk()
            ->assertSee('1 venue')
            ->assertSee('1 court')
            ->assertSee('Pickleball Courts in Bunawan')
            ->assertDontSee('data-location-stat="minimum-price"', false)
            ->assertDontSee('data-minimum-hourly-price=', false)
            ->assertDontSee('From ₱0/hour')
            ->assertDontSee('rates starting from ₱0');
    }

    public function test_public_city_names_are_human_friendly_without_changing_canonical_location_data(): void
    {
        PsgcLocation::query()->create([
            'code' => '1903617000',
            'parent_code' => null,
            'name' => 'City of Marawi',
            'level' => 'city',
            'type' => 'component_city',
            'source_version' => 'test',
        ]);
        [$venue, , $sport] = $this->publicVenue([
            'name' => 'Marawi Pickleball Center',
            'slug' => 'marawi-pickleball-center',
            'city' => 'City of Marawi',
            'city_slug' => 'city-of-marawi',
            'province' => 'Lanao del Sur',
            'province_slug' => 'lanao-del-sur',
            'psgc_city_municipality_code' => '1903617000',
        ], [], [
            'name' => 'Pickleball',
            'slug' => 'pickleball',
        ]);
        $cityUrl = route('marketplace.courts.city', 'city-of-marawi');
        $sportCityUrl = route('marketplace.courts.sport-city', [$sport->slug, 'city-of-marawi']);

        $this->get($cityUrl)
            ->assertOk()
            ->assertSee('<title>Sports Courts in Marawi City | Find &amp; Book Courts | FinACourt</title>', false)
            ->assertSee('<h1 class="mt-3 max-w-5xl text-4xl font-semibold tracking-[-0.035em] text-slate-950 sm:text-5xl">Sports Courts in Marawi City</h1>', false)
            ->assertSee('<meta name="description" content="Find sports courts in Marawi City, Lanao del Sur. Compare 1 venue and 1 court, including pickleball, from ₱650/hour on FinACourt.">', false)
            ->assertSee('<link rel="canonical" href="'.$cityUrl.'">', false)
            ->assertSee('Marawi City, Lanao del Sur')
            ->assertSee('data-location-summary', false)
            ->assertSee('1 venue')
            ->assertSee('1 court')
            ->assertSee('Sports available in Marawi City')
            ->assertSee('1 venue · 1 court')
            ->assertSee('value="city-of-marawi"', false)
            ->assertSee('Marawi City')
            ->assertDontSee('/courts/marawi-city', false);

        $this->get(route('marketplace.courts.index', ['city' => 'city-of-marawi']))
            ->assertOk()
            ->assertSee($venue->name)
            ->assertSee('value="city-of-marawi" selected', false)
            ->assertSee('Marawi City')
            ->assertDontSee('/courts/marawi-city', false);

        $this->get($sportCityUrl)
            ->assertOk()
            ->assertSee('<title>Pickleball Courts in Marawi City | Find &amp; Book | FinACourt</title>', false)
            ->assertSee('Pickleball Courts in Marawi City')
            ->assertSee('Looking for a pickleball court in Marawi City?')
            ->assertSee('<link rel="canonical" href="'.$sportCityUrl.'">', false);

        $this->get(route('marketplace.venues.show', $venue->slug))
            ->assertOk()
            ->assertSee('Marawi City, Lanao del Sur')
            ->assertSee('"addressLocality":"City of Marawi"', false)
            ->assertSee('"name":"Marawi City"', false);

        $this->get(route('marketplace.sitemap'))
            ->assertOk()
            ->assertSee($cityUrl, false)
            ->assertSee($sportCityUrl, false)
            ->assertDontSee('/courts/marawi-city', false)
            ->assertDontSee('/pickleball/marawi-city', false);

        $venue->refresh();
        $this->assertSame('City of Marawi', $venue->city);
        $this->assertSame('city-of-marawi', $venue->city_slug);
        $this->assertSame('1903617000', $venue->psgc_city_municipality_code);

        $search = AnalyticsEvent::query()
            ->where('event_type', AnalyticsEventType::MarketplaceSearch)
            ->latest('id')
            ->firstOrFail();
        $this->assertSame('city-of-marawi', $search->demand_city_slug);

        $this->get(route('marketplace.courts.city', 'marawi-city'))->assertNotFound();
        $this->get(route('marketplace.courts.sport-city', [$sport->slug, 'marawi-city']))->assertNotFound();
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

    public function test_court_blocks_are_hidden_from_public_availability_and_filtered_search(): void
    {
        [$venue, $resource] = $this->publicVenue();
        $date = CarbonImmutable::now('Asia/Manila')->addDays(7);
        $start = $date->setTime(17, 0);

        CourtAvailabilityBlock::query()->create([
            'organization_id' => $venue->organization_id,
            'venue_id' => $venue->getKey(),
            'resource_id' => $resource->getKey(),
            'starts_at' => $start->utc(),
            'ends_at' => $start->addHour()->utc(),
            'timezone' => 'Asia/Manila',
            'is_all_day' => false,
            'reason' => 'Court maintenance',
        ]);

        $this->get(route('marketplace.venues.show', [
            'venueSlug' => $venue->slug,
            'resource' => $resource->getKey(),
            'date' => $date->toDateString(),
        ]))
            ->assertOk()
            ->assertSee('data-unavailable-slot data-start="17:00"', false)
            ->assertDontSee('aria-label="Select 17:00 to 18:00"', false)
            ->assertDontSee('Court maintenance');

        $this->get(route('marketplace.courts.index', [
            'date' => $date->toDateString(),
            'start_time' => '17:00',
            'duration_minutes' => 60,
        ]))
            ->assertOk()
            ->assertDontSee($venue->name);
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
