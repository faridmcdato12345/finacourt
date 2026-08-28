<?php

namespace Database\Seeders;

use App\Bookings\CreateBooking;
use App\Enums\AcquisitionSource;
use App\Enums\AnalyticsEventType;
use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\DemandSearchOutcome;
use App\Enums\MembershipRole;
use App\Enums\OrganizationPermission;
use App\Enums\PromotionDiscountType;
use App\Enums\PromotionGoal;
use App\Enums\PromotionStatus;
use App\Enums\PromotionType;
use App\Enums\ResourceSetting;
use App\Enums\ResourceType;
use App\Enums\Weekday;
use App\Models\Amenity;
use App\Models\AnalyticsEvent;
use App\Models\Booking;
use App\Models\CourtResource;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Promotion;
use App\Models\PromotionSlot;
use App\Models\Sport;
use App\Models\User;
use App\Models\Venue;
use Carbon\CarbonImmutable;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Pilot demo data may only be seeded in local or testing environments.');
        }

        $this->call(PsgcLocationSeeder::class);

        if (! config('pilot.demo_seed_enabled')) {
            $this->command?->warn('Pilot demo seeding is disabled. Set PILOT_DEMO_SEED=true only in local/testing.');

            return;
        }

        $organization = Organization::query()->firstOrCreate(
            ['slug' => 'demo-courts'],
            ['name' => 'Demo Courts', 'timezone' => 'Asia/Manila'],
        );

        $owner = User::query()->updateOrCreate(
            ['email' => 'owner@example.com'],
            ['name' => 'Demo Owner', 'password' => 'password', 'is_platform_admin' => false],
        );

        $staff = User::query()->updateOrCreate(
            ['email' => 'staff@example.com'],
            ['name' => 'Demo Staff', 'password' => 'password', 'is_platform_admin' => false],
        );

        $player = User::query()->updateOrCreate(
            ['email' => 'player@example.com'],
            ['name' => 'Demo Player', 'password' => 'password', 'is_platform_admin' => false],
        );

        User::query()->updateOrCreate(
            ['email' => 'admin@example.com'],
            ['name' => 'Platform Admin', 'password' => 'password', 'is_platform_admin' => true],
        );

        Membership::query()->updateOrCreate(
            ['organization_id' => $organization->getKey(), 'user_id' => $owner->getKey()],
            ['role' => MembershipRole::Owner, 'permissions' => null, 'joined_at' => now()],
        );

        Membership::query()->updateOrCreate(
            ['organization_id' => $organization->getKey(), 'user_id' => $staff->getKey()],
            [
                'role' => MembershipRole::Staff,
                'permissions' => [OrganizationPermission::ViewDashboard->value],
                'joined_at' => now(),
            ],
        );

        $sports = collect([
            ['name' => 'Badminton', 'slug' => 'badminton'],
            ['name' => 'Basketball', 'slug' => 'basketball'],
            ['name' => 'Futsal', 'slug' => 'futsal'],
            ['name' => 'Pickleball', 'slug' => 'pickleball'],
            ['name' => 'Tennis', 'slug' => 'tennis'],
            ['name' => 'Volleyball', 'slug' => 'volleyball'],
        ])->mapWithKeys(function (array $sport) {
            $model = Sport::query()->updateOrCreate(
                ['slug' => $sport['slug']],
                ['name' => $sport['name'], 'is_active' => true],
            );

            return [$sport['slug'] => $model];
        });

        $amenities = collect([
            ['name' => 'Equipment Rental', 'slug' => 'equipment-rental'],
            ['name' => 'Lockers', 'slug' => 'lockers'],
            ['name' => 'Parking', 'slug' => 'parking'],
            ['name' => 'Restrooms', 'slug' => 'restrooms'],
            ['name' => 'Seating', 'slug' => 'seating'],
            ['name' => 'Showers', 'slug' => 'showers'],
            ['name' => 'Water Station', 'slug' => 'water-station'],
        ])->mapWithKeys(function (array $amenity) {
            $model = Amenity::query()->updateOrCreate(
                ['slug' => $amenity['slug']],
                ['name' => $amenity['name'], 'is_active' => true],
            );

            return [$amenity['slug'] => $model];
        });

        $venue = Venue::query()->updateOrCreate(
            ['slug' => 'demo-courts-makati'],
            [
                'organization_id' => $organization->getKey(),
                'name' => 'Demo Courts Makati',
                'description' => 'A seeded facility for exploring the public marketplace and owner operations workspace.',
                'address' => '123 Sports Avenue',
                'city' => 'Makati',
                'city_slug' => Str::slug('Makati'),
                'province' => 'Metro Manila',
                'province_slug' => Str::slug('Metro Manila'),
                'phone' => '+63 2 8123 4567',
                'email' => 'courts@example.com',
                'is_published' => true,
                'claimed_at' => now(),
            ],
        );

        $venue->sports()->sync($sports->pluck('id')->all());
        $venue->amenities()->sync([
            $amenities['parking']->getKey(),
            $amenities['restrooms']->getKey(),
            $amenities['water-station']->getKey(),
        ]);

        foreach (Weekday::cases() as $day) {
            $venue->operatingHours()->updateOrCreate(
                ['day_of_week' => $day],
                ['is_closed' => false, 'opens_at' => '08:00', 'closes_at' => '22:00'],
            );
        }

        $courtCatalog = [
            ['name' => 'Badminton Court 1', 'sport' => 'badminton', 'setting' => ResourceSetting::Indoor, 'rate' => 650, 'discount' => 20, 'campaign' => 'DEAL-DEMO-WEEKDAY20', 'title' => 'Weekday badminton deal', 'days' => [1, 2, 3, 4, 5], 'starts_at' => '08:00', 'ends_at' => '17:00'],
            ['name' => 'Badminton Court 2', 'sport' => 'badminton', 'setting' => ResourceSetting::Indoor, 'rate' => 700, 'discount' => 15, 'campaign' => 'DEAL-DEMO-BADMINTON2-15', 'title' => 'Badminton Court 2 special'],
            ['name' => 'Badminton Court 3', 'sport' => 'badminton', 'setting' => ResourceSetting::Covered, 'rate' => 600, 'discount' => 25, 'campaign' => 'DEAL-DEMO-BADMINTON3-25', 'title' => 'Covered court saver'],
            ['name' => 'Pickleball Court 1', 'sport' => 'pickleball', 'setting' => ResourceSetting::Covered, 'rate' => 550, 'discount' => 10, 'campaign' => 'DEAL-DEMO-PICKLEBALL1-10', 'title' => 'Pickleball starter deal'],
            ['name' => 'Pickleball Court 2', 'sport' => 'pickleball', 'setting' => ResourceSetting::Outdoor, 'rate' => 500, 'discount' => 12, 'campaign' => 'DEAL-DEMO-PICKLEBALL2-12', 'title' => 'Outdoor pickleball offer'],
            ['name' => 'Tennis Court 1', 'sport' => 'tennis', 'setting' => ResourceSetting::Outdoor, 'rate' => 900, 'discount' => 15, 'campaign' => 'DEAL-DEMO-TENNIS1-15', 'title' => 'Tennis Court 1 deal'],
            ['name' => 'Tennis Court 2', 'sport' => 'tennis', 'setting' => ResourceSetting::Covered, 'rate' => 950, 'discount' => 10, 'campaign' => 'DEAL-DEMO-TENNIS2-10', 'title' => 'Covered tennis special'],
            ['name' => 'Basketball Court 1', 'sport' => 'basketball', 'setting' => ResourceSetting::Indoor, 'rate' => 1200, 'discount' => 10, 'campaign' => 'DEAL-DEMO-BASKETBALL1-10', 'title' => 'Basketball team discount'],
            ['name' => 'Volleyball Court 1', 'sport' => 'volleyball', 'setting' => ResourceSetting::Indoor, 'rate' => 850, 'discount' => 20, 'campaign' => 'DEAL-DEMO-VOLLEYBALL1-20', 'title' => 'Volleyball court deal'],
            ['name' => 'Futsal Court 1', 'sport' => 'futsal', 'setting' => ResourceSetting::Covered, 'rate' => 1100, 'discount' => 15, 'campaign' => 'DEAL-DEMO-FUTSAL1-15', 'title' => 'Futsal group special'],
        ];

        $seededInventory = collect($courtCatalog)->mapWithKeys(function (array $court) use ($organization, $sports, $venue): array {
            $resource = CourtResource::query()->updateOrCreate(
                ['venue_id' => $venue->getKey(), 'name' => $court['name']],
                [
                    'sport_id' => $sports[$court['sport']]->getKey(),
                    'resource_type' => ResourceType::Court,
                    'setting' => $court['setting'],
                    'is_active' => true,
                    'base_hourly_rate' => $court['rate'],
                    'currency' => 'PHP',
                    'booking_increment_minutes' => 60,
                ],
            );

            $promotion = Promotion::query()->updateOrCreate(
                ['campaign_token' => $court['campaign']],
                [
                    'organization_id' => $organization->getKey(),
                    'venue_id' => $venue->getKey(),
                    'resource_id' => $resource->getKey(),
                    'title' => $court['title'],
                    'description' => "Save {$court['discount']}% when booking {$court['name']} during this demo campaign.",
                    'promotion_type' => PromotionType::Deal,
                    'goal' => PromotionGoal::FillEmptySlots,
                    'status' => PromotionStatus::Active,
                    'audience_sport_id' => $sports[$court['sport']]->getKey(),
                    'audience_city_slug' => $venue->city_slug,
                    'discount_type' => PromotionDiscountType::Percentage,
                    'discount_value' => number_format($court['discount'], 2, '.', ''),
                    'starts_on' => now($organization->timezone)->toDateString(),
                    'ends_on' => now($organization->timezone)->addMonths(3)->toDateString(),
                    'targets_specific_slots' => false,
                    'days_of_week' => $court['days'] ?? null,
                    'starts_at_time' => $court['starts_at'] ?? null,
                    'ends_at_time' => $court['ends_at'] ?? null,
                    'is_active' => true,
                    'is_public' => true,
                ],
            );

            return [$court['name'] => ['resource' => $resource, 'promotion' => $promotion]];
        });

        $resource = $seededInventory['Badminton Court 1']['resource'];
        $promotion = $seededInventory['Badminton Court 1']['promotion'];
        $lastMinuteResource = $seededInventory['Pickleball Court 2']['resource'];
        $lastMinuteDate = now($organization->timezone)->addDay()->toDateString();
        $lastMinutePromotion = Promotion::query()->updateOrCreate(
            ['campaign_token' => 'DEAL-DEMO-TONIGHT-SLOTS'],
            [
                'organization_id' => $organization->getKey(),
                'venue_id' => $venue->getKey(),
                'resource_id' => $lastMinuteResource->getKey(),
                'audience_sport_id' => $lastMinuteResource->sport_id,
                'audience_city_slug' => $venue->city_slug,
                'title' => 'Tomorrow pickleball openings',
                'description' => 'Two owner-approved upcoming court slots from the Promotion Engine V2 demo.',
                'promotion_type' => PromotionType::SpecificSlots,
                'goal' => PromotionGoal::PromoteTodayOrTonight,
                'status' => PromotionStatus::Active,
                'discount_type' => PromotionDiscountType::Percentage,
                'discount_value' => '15.00',
                'starts_on' => now($organization->timezone)->toDateString(),
                'ends_on' => $lastMinuteDate,
                'targets_specific_slots' => true,
                'days_of_week' => null,
                'starts_at_time' => null,
                'ends_at_time' => null,
                'is_active' => true,
                'is_public' => true,
            ],
        );
        foreach ([['18:00', '19:00'], ['19:00', '20:00']] as $index => [$start, $end]) {
            PromotionSlot::query()->updateOrCreate(
                ['slot_token' => 'SLOT-DEMO-TONIGHT-'.($index + 1)],
                [
                    'promotion_id' => $lastMinutePromotion->getKey(),
                    'resource_id' => $lastMinuteResource->getKey(),
                    'slot_date' => $lastMinuteDate,
                    'starts_at_time' => $start,
                    'ends_at_time' => $end,
                ],
            );
        }

        if (! Booking::query()->where('reference', 'BK-DEMO-0001')->exists()) {
            try {
                $booking = app(CreateBooking::class)->handle(
                    $organization->getKey(),
                    $owner,
                    [
                        'resource_id' => $venue->resources()->where('name', 'Badminton Court 1')->value('id'),
                        'booking_date' => now($organization->timezone)->addDay()->toDateString(),
                        'start_time' => '10:00',
                        'end_time' => '11:00',
                        'status' => BookingStatus::Confirmed->value,
                        'source' => 'walk_in',
                        'hold_minutes' => null,
                        'customer_name' => 'Demo Walk-in Customer',
                        'customer_email' => null,
                        'customer_phone' => null,
                        'notes' => 'Seeded booking for the owner schedule.',
                    ],
                );
                $booking->update(['reference' => 'BK-DEMO-0001']);
            } catch (ValidationException) {
                // Existing local demo activity may already occupy this slot.
            }
        }

        $promotionDate = now($organization->timezone)->addDays(2);

        while ($promotionDate->isWeekend()) {
            $promotionDate = $promotionDate->addDay();
        }

        $promotedBooking = Booking::query()->where('reference', 'BK-DEMO-PROMO1')->first();

        if (! $promotedBooking) {
            try {
                $promotedBooking = app(CreateBooking::class)->handle(
                    $organization->getKey(),
                    $player,
                    [
                        'resource_id' => $resource->getKey(),
                        'booking_date' => $promotionDate->toDateString(),
                        'start_time' => '14:00',
                        'end_time' => '15:00',
                        'status' => BookingStatus::Confirmed->value,
                        'source' => BookingSource::Marketplace->value,
                        'traffic_source' => AcquisitionSource::MarketplacePromotion->value,
                        'traffic_source_detail' => $promotion->campaign_token,
                        'customer_name' => $player->name,
                        'customer_email' => $player->email,
                        'customer_phone' => null,
                        'notes' => 'Local-only promoted pilot booking.',
                        'create_payment' => true,
                        'campaign' => $promotion->campaign_token,
                    ],
                    $player,
                );
                $promotedBooking->update(['reference' => 'BK-DEMO-PROMO1']);
            } catch (ValidationException) {
                // Existing local demo activity may already occupy this slot.
            }
        }

        $secondOrganization = Organization::query()->firstOrCreate(
            ['slug' => 'northside-sports'],
            ['name' => 'Northside Sports', 'timezone' => 'Asia/Manila'],
        );
        $secondOwner = User::query()->updateOrCreate(
            ['email' => 'northside.owner@example.com'],
            ['name' => 'Northside Owner', 'password' => 'password', 'is_platform_admin' => false],
        );
        Membership::query()->updateOrCreate(
            ['organization_id' => $secondOrganization->getKey(), 'user_id' => $secondOwner->getKey()],
            ['role' => MembershipRole::Owner, 'permissions' => null, 'joined_at' => now()],
        );
        $secondVenue = Venue::query()->updateOrCreate(
            ['slug' => 'northside-sports-quezon-city'],
            [
                'organization_id' => $secondOrganization->getKey(),
                'name' => 'Northside Sports Quezon City',
                'description' => 'A second local-only tenant used to demonstrate strict organization isolation.',
                'address' => '45 North Avenue',
                'city' => 'Quezon City',
                'city_slug' => Str::slug('Quezon City'),
                'province' => 'Metro Manila',
                'province_slug' => Str::slug('Metro Manila'),
                'email' => 'northside@example.com',
                'is_published' => true,
                'claimed_at' => now(),
            ],
        );
        $secondVenue->sports()->sync([$sports['pickleball']->getKey()]);
        $secondVenue->amenities()->sync([$amenities['parking']->getKey(), $amenities['restrooms']->getKey()]);

        foreach (Weekday::cases() as $day) {
            $secondVenue->operatingHours()->updateOrCreate(
                ['day_of_week' => $day],
                ['is_closed' => false, 'opens_at' => '07:00', 'closes_at' => '21:00'],
            );
        }

        $secondResource = CourtResource::query()->updateOrCreate(
            ['venue_id' => $secondVenue->getKey(), 'name' => 'Pickleball Court A'],
            [
                'sport_id' => $sports['pickleball']->getKey(),
                'resource_type' => ResourceType::Court,
                'setting' => ResourceSetting::Covered,
                'is_active' => true,
                'base_hourly_rate' => 500,
                'currency' => 'PHP',
                'booking_increment_minutes' => 60,
            ],
        );

        if (! Booking::query()->where('reference', 'BK-NORTHSIDE-0001')->exists()) {
            try {
                $booking = app(CreateBooking::class)->handle(
                    $secondOrganization->getKey(),
                    $secondOwner,
                    [
                        'resource_id' => $secondResource->getKey(),
                        'booking_date' => now($secondOrganization->timezone)->addDay()->toDateString(),
                        'start_time' => '09:00',
                        'end_time' => '10:00',
                        'status' => BookingStatus::Confirmed->value,
                        'source' => BookingSource::Phone->value,
                        'customer_name' => 'Northside Demo Customer',
                        'customer_email' => null,
                        'customer_phone' => null,
                        'notes' => 'Local-only second-tenant booking.',
                    ],
                );
                $booking->update(['reference' => 'BK-NORTHSIDE-0001']);
            } catch (ValidationException) {
                // Existing local demo activity may already occupy this slot.
            }
        }

        $this->seedSingleCourtOwners($sports, $amenities);

        if ($promotedBooking) {
            $this->seedAnalytics($venue, $resource, $promotion, $promotedBooking);
        }

    }

    /**
     * @param  Collection<string, Sport>  $sports
     * @param  Collection<string, Amenity>  $amenities
     */
    private function seedSingleCourtOwners(Collection $sports, Collection $amenities): void
    {
        $ownerCatalog = [
            ['number' => 1, 'name' => 'Ari Santos', 'business' => 'Taguig Badminton Hub', 'slug' => 'taguig-badminton-hub', 'city' => 'Taguig', 'province' => 'Metro Manila', 'sport' => 'badminton', 'setting' => ResourceSetting::Indoor, 'rate' => 650],
            ['number' => 2, 'name' => 'Bea Reyes', 'business' => 'Pasig Pickleball Club', 'slug' => 'pasig-pickleball-club', 'city' => 'Pasig', 'province' => 'Metro Manila', 'sport' => 'pickleball', 'setting' => ResourceSetting::Covered, 'rate' => 550],
            ['number' => 3, 'name' => 'Carlo Mendoza', 'business' => 'Quezon City Tennis Center', 'slug' => 'quezon-city-tennis-center', 'city' => 'Quezon City', 'province' => 'Metro Manila', 'sport' => 'tennis', 'setting' => ResourceSetting::Outdoor, 'rate' => 900],
            ['number' => 4, 'name' => 'Dana Cruz', 'business' => 'Manila Basketball Gym', 'slug' => 'manila-basketball-gym', 'city' => 'Manila', 'province' => 'Metro Manila', 'sport' => 'basketball', 'setting' => ResourceSetting::Indoor, 'rate' => 1200],
            ['number' => 5, 'name' => 'Enzo Garcia', 'business' => 'Mandaluyong Volleyball Hall', 'slug' => 'mandaluyong-volleyball-hall', 'city' => 'Mandaluyong', 'province' => 'Metro Manila', 'sport' => 'volleyball', 'setting' => ResourceSetting::Indoor, 'rate' => 850],
            ['number' => 6, 'name' => 'Faith Lim', 'business' => 'Makati Futsal Arena', 'slug' => 'makati-futsal-arena', 'city' => 'Makati', 'province' => 'Metro Manila', 'sport' => 'futsal', 'setting' => ResourceSetting::Covered, 'rate' => 1100],
            ['number' => 7, 'name' => 'Gio Navarro', 'business' => 'Cebu Badminton House', 'slug' => 'cebu-badminton-house', 'city' => 'Cebu City', 'province' => 'Cebu', 'sport' => 'badminton', 'setting' => ResourceSetting::Indoor, 'rate' => 600],
            ['number' => 8, 'name' => 'Hazel Flores', 'business' => 'Davao Pickleball Park', 'slug' => 'davao-pickleball-park', 'city' => 'Davao City', 'province' => 'Davao del Sur', 'sport' => 'pickleball', 'setting' => ResourceSetting::Outdoor, 'rate' => 500],
            ['number' => 9, 'name' => 'Ivan Bautista', 'business' => 'Pasay Tennis Court', 'slug' => 'pasay-tennis-court', 'city' => 'Pasay', 'province' => 'Metro Manila', 'sport' => 'tennis', 'setting' => ResourceSetting::Covered, 'rate' => 950],
            ['number' => 10, 'name' => 'Jade Aquino', 'business' => 'Paranaque Basketball Center', 'slug' => 'paranaque-basketball-center', 'city' => 'Paranaque', 'province' => 'Metro Manila', 'sport' => 'basketball', 'setting' => ResourceSetting::Indoor, 'rate' => 1000],
        ];

        foreach ($ownerCatalog as $entry) {
            $email = sprintf('court.owner%02d@example.com', $entry['number']);
            $organization = Organization::query()->updateOrCreate(
                ['slug' => $entry['slug']],
                ['name' => $entry['business'], 'timezone' => 'Asia/Manila'],
            );
            $owner = User::query()->updateOrCreate(
                ['email' => $email],
                ['name' => $entry['name'], 'password' => 'password', 'is_platform_admin' => false],
            );

            Membership::query()->updateOrCreate(
                ['organization_id' => $organization->getKey(), 'user_id' => $owner->getKey()],
                ['role' => MembershipRole::Owner, 'permissions' => null, 'joined_at' => now()],
            );

            $venue = Venue::query()->updateOrCreate(
                ['slug' => $entry['slug']],
                [
                    'organization_id' => $organization->getKey(),
                    'name' => $entry['business'],
                    'description' => "A local demo venue managed by {$entry['name']} with one bookable {$entry['sport']} court.",
                    'address' => $entry['number'].' Court Street',
                    'city' => $entry['city'],
                    'city_slug' => Str::slug($entry['city']),
                    'province' => $entry['province'],
                    'province_slug' => Str::slug($entry['province']),
                    'email' => $email,
                    'is_published' => true,
                    'claimed_at' => now(),
                ],
            );

            $sport = $sports[$entry['sport']];
            $venue->sports()->sync([$sport->getKey()]);
            $venue->amenities()->sync([
                $amenities['parking']->getKey(),
                $amenities['restrooms']->getKey(),
            ]);

            foreach (Weekday::cases() as $day) {
                $venue->operatingHours()->updateOrCreate(
                    ['day_of_week' => $day],
                    ['is_closed' => false, 'opens_at' => '08:00', 'closes_at' => '22:00'],
                );
            }

            CourtResource::query()->updateOrCreate(
                ['venue_id' => $venue->getKey(), 'name' => 'Court 1'],
                [
                    'sport_id' => $sport->getKey(),
                    'resource_type' => ResourceType::Court,
                    'setting' => $entry['setting'],
                    'is_active' => true,
                    'base_hourly_rate' => $entry['rate'],
                    'currency' => 'PHP',
                    'booking_increment_minutes' => 60,
                ],
            );
        }
    }

    private function seedAnalytics(
        Venue $venue,
        CourtResource $resource,
        Promotion $promotion,
        Booking $booking,
    ): void {
        $now = now('UTC');
        $visitorHash = hash('sha256', 'local-pilot-demo-visitor');
        $events = [
            AnalyticsEventType::VenueImpression,
            AnalyticsEventType::VenueProfileView,
            AnalyticsEventType::AvailabilityView,
            AnalyticsEventType::PromotionImpression,
            AnalyticsEventType::PromotionClick,
            AnalyticsEventType::BookingStart,
            AnalyticsEventType::CompletedBooking,
        ];

        foreach ($events as $event) {
            $isPromotionEvent = in_array($event, [
                AnalyticsEventType::PromotionImpression,
                AnalyticsEventType::PromotionClick,
                AnalyticsEventType::BookingStart,
                AnalyticsEventType::CompletedBooking,
            ], true);
            $isBookingEvent = in_array($event, [
                AnalyticsEventType::BookingStart,
                AnalyticsEventType::CompletedBooking,
            ], true);

            AnalyticsEvent::query()->updateOrCreate(
                ['dedupe_key' => hash('sha256', "pilot-demo|{$event->value}")],
                [
                    'organization_id' => $venue->organization_id,
                    'venue_id' => $venue->getKey(),
                    'resource_id' => in_array($event, [AnalyticsEventType::AvailabilityView, AnalyticsEventType::BookingStart, AnalyticsEventType::CompletedBooking], true)
                        ? $resource->getKey()
                        : null,
                    'promotion_id' => $isPromotionEvent ? $promotion->getKey() : null,
                    'booking_id' => $isBookingEvent ? $booking->getKey() : null,
                    'event_type' => $event,
                    'visitor_hash' => $event === AnalyticsEventType::CompletedBooking ? null : $visitorHash,
                    'traffic_source' => AcquisitionSource::MarketplacePromotion->value,
                    'source_detail' => $promotion->campaign_token,
                    'is_demo' => true,
                    'metadata' => ['local_demo' => true],
                    'occurred_at' => $now,
                ],
            );
        }

        $searchDemand = [
            ['city' => 'makati', 'sport' => 'badminton', 'date' => now('Asia/Manila')->addDay()->toDateString(), 'start_time' => '18:00', 'duration_minutes' => 60, 'result_count' => 4],
            ['city' => 'makati', 'sport' => 'badminton', 'date' => now('Asia/Manila')->addDay()->toDateString(), 'start_time' => '21:00', 'duration_minutes' => 60, 'result_count' => 0],
            ['city' => 'makati', 'sport' => 'pickleball', 'duration_minutes' => 60, 'result_count' => 2],
            ['city' => 'taguig', 'sport' => 'tennis', 'date' => now('Asia/Manila')->addDays(2)->toDateString(), 'start_time' => '19:00', 'duration_minutes' => 60, 'result_count' => 0],
            ['city' => 'pasig', 'sport' => 'pickleball', 'date' => now('Asia/Manila')->addDays(2)->toDateString(), 'start_time' => '18:00', 'duration_minutes' => 60, 'result_count' => 1],
            ['city' => 'pasig', 'sport' => 'badminton', 'duration_minutes' => 60, 'result_count' => 0],
            ['city' => 'cebu-city', 'sport' => 'badminton', 'date' => now('Asia/Manila')->addDays(3)->toDateString(), 'start_time' => '17:00', 'duration_minutes' => 60, 'result_count' => 1],
            ['city' => 'cebu-city', 'sport' => 'volleyball', 'date' => now('Asia/Manila')->addDays(3)->toDateString(), 'start_time' => '20:00', 'duration_minutes' => 120, 'result_count' => 0],
            ['city' => 'davao-city', 'sport' => 'pickleball', 'duration_minutes' => 60, 'result_count' => 1],
            ['city' => 'davao-city', 'sport' => 'futsal', 'date' => now('Asia/Manila')->addDays(4)->toDateString(), 'start_time' => '19:00', 'duration_minutes' => 120, 'result_count' => 0],
            ['city' => 'manila', 'sport' => 'basketball', 'date' => now('Asia/Manila')->addDays(5)->toDateString(), 'start_time' => '18:00', 'duration_minutes' => 120, 'result_count' => 1],
            ['city' => 'quezon-city', 'sport' => 'tennis', 'date' => now('Asia/Manila')->addDays(5)->toDateString(), 'start_time' => '16:00', 'duration_minutes' => 60, 'result_count' => 1],
        ];

        foreach ($searchDemand as $index => $metadata) {
            $resultCount = (int) $metadata['result_count'];
            $startTime = $metadata['start_time'] ?? null;
            $duration = (int) ($metadata['duration_minutes'] ?? 60);
            $endTime = $startTime
                ? CarbonImmutable::createFromFormat('!H:i', $startTime, 'UTC')->addMinutes($duration)->format('H:i')
                : null;
            $outcome = $resultCount > 0
                ? DemandSearchOutcome::ResultsAvailable
                : DemandSearchOutcome::NoResults;

            AnalyticsEvent::query()->updateOrCreate(
                ['dedupe_key' => hash('sha256', "pilot-demo|marketplace-search|{$index}")],
                [
                    'organization_id' => null,
                    'venue_id' => null,
                    'resource_id' => null,
                    'promotion_id' => null,
                    'booking_id' => null,
                    'event_type' => AnalyticsEventType::MarketplaceSearch,
                    'demand_city_slug' => $metadata['city'] ?? null,
                    'demand_sport_slug' => $metadata['sport'] ?? null,
                    'demand_setting' => $metadata['setting'] ?? null,
                    'requested_date' => $metadata['date'] ?? null,
                    'requested_start_time' => $startTime,
                    'requested_end_time' => $endTime,
                    'duration_minutes' => $duration,
                    'maximum_hourly_rate' => $metadata['max_price'] ?? null,
                    'matching_venue_count' => $resultCount,
                    'available_result_count' => $resultCount,
                    'search_outcome' => $outcome,
                    'entry_context' => 'local_demo',
                    'is_demo' => true,
                    'visitor_hash' => hash('sha256', "local-pilot-searcher-{$index}"),
                    'traffic_source' => $index % 3 === 0
                        ? AcquisitionSource::Referral->value
                        : AcquisitionSource::Direct->value,
                    'source_detail' => $index % 3 === 0 ? 'local-demo-partner.example' : null,
                    'metadata' => [
                        ...$metadata,
                        'matching_venue_count' => $resultCount,
                        'available_result_count' => $resultCount,
                        'search_outcome' => $outcome->value,
                        'entry_context' => 'local_demo',
                        'schema_version' => 2,
                        'local_demo' => true,
                    ],
                    'occurred_at' => $now,
                ],
            );
        }

        $promotion->update([
            'impressions_count' => 1,
            'clicks_count' => 1,
            'booking_starts_count' => max(1, $promotion->booking_starts_count),
        ]);
    }
}
