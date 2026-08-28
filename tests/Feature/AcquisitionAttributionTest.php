<?php

namespace Tests\Feature;

use App\Analytics\AnalyticsRecorder;
use App\Analytics\TrafficAttribution;
use App\Enums\AcquisitionSource;
use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Enums\PromotionType;
use App\Models\Booking;
use App\Models\BookingAttribution;
use App\Models\CourtResource;
use App\Models\Membership;
use App\Models\OperatingHour;
use App\Models\Organization;
use App\Models\Promotion;
use App\Models\PromotionSlot;
use App\Models\Sport;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AcquisitionAttributionTest extends TestCase
{
    use RefreshDatabase;

    public function test_source_taxonomy_is_centralized_and_stable(): void
    {
        $this->assertSame([
            'marketplace_organic',
            'marketplace_promotion',
            'customer_reactivation',
            'google_organic',
            'google_maps',
            'facebook',
            'instagram',
            'tiktok',
            'qr_code',
            'referral',
            'sales_partner',
            'direct',
            'unknown',
        ], array_column(AcquisitionSource::cases(), 'value'));
    }

    public function test_first_touch_is_preserved_and_last_touch_updates_only_for_new_attribution_signals(): void
    {
        $session = app('session')->driver();
        $first = $this->capture('/courts?utm_source=facebook&utm_medium=paid-social&utm_campaign=opening-week', $session);
        $second = $this->capture('/courts?utm_source=instagram&utm_medium=social&utm_campaign=weekend', $session);
        $internal = $this->capture('/venues/sample-venue', $session, 'http://localhost/courts');

        $this->assertSame(AcquisitionSource::Facebook, $first['first_touch']['source']);
        $this->assertSame(AcquisitionSource::Facebook, $second['first_touch']['source']);
        $this->assertSame(AcquisitionSource::Instagram, $second['last_touch']['source']);
        $this->assertSame('weekend', $second['last_touch']['campaign']);
        $this->assertSame(AcquisitionSource::Instagram, $internal['last_touch']['source']);
        $this->assertSame($second['last_touch']['seen_at'], $internal['last_touch']['seen_at']);
    }

    public function test_expired_session_context_starts_a_new_direct_attribution_window(): void
    {
        $session = app('session')->driver();
        $this->travelTo(now('UTC')->startOfDay());
        $first = $this->capture('/courts?utm_source=facebook&utm_campaign=old-window', $session);
        $this->travel((int) config('attribution.lookback_days') + 1)->days();
        $expired = $this->capture('/venues/sample-venue', $session);

        $this->assertSame(AcquisitionSource::Facebook, $first['source']);
        $this->assertSame(AcquisitionSource::Direct, $expired['source']);
        $this->assertSame(AcquisitionSource::Direct, $expired['first_touch']['source']);
        $this->assertNotSame($first['seen_at'], $expired['seen_at']);
    }

    public function test_qr_referral_partner_and_unknown_markers_are_parsed_without_arbitrary_sources(): void
    {
        $qr = $this->capture('/courts?qr=QR-MAKATI-01', app('session')->driver());
        $referral = $this->capture('/courts?ref=PLAYER-FRIEND-02', app('session')->driver());
        $partner = $this->capture('/courts?partner=REP-DAVAO-03', app('session')->driver());
        $unknown = $this->capture('/courts?acq_source=browser-invented-source', app('session')->driver());
        $unverifiedPromotion = $this->capture('/courts?acq_source=marketplace_promotion', app('session')->driver());
        $unverifiedReactivation = $this->capture('/courts?acq_source=customer_reactivation', app('session')->driver());

        $this->assertSame(AcquisitionSource::QrCode, $qr['source']);
        $this->assertSame('QR-MAKATI-01', $qr['campaign']);
        $this->assertSame(AcquisitionSource::Referral, $referral['source']);
        $this->assertSame('PLAYER-FRIEND-02', $referral['referral_code']);
        $this->assertSame(AcquisitionSource::SalesPartner, $partner['source']);
        $this->assertSame('REP-DAVAO-03', $partner['partner_code']);
        $this->assertSame(AcquisitionSource::Unknown, $unknown['source']);
        $this->assertNull($unknown['referral_code']);
        $this->assertNull($unknown['partner_code']);
        $this->assertSame(AcquisitionSource::Unknown, $unverifiedPromotion['source']);
        $this->assertSame(AcquisitionSource::Unknown, $unverifiedReactivation['source']);
    }

    public function test_booking_stores_immutable_first_last_and_promotion_attribution_snapshots(): void
    {
        [$organization, $venue, $resource] = $this->inventory('snapshot-attribution');
        $player = User::factory()->create();
        $date = now($organization->timezone)->addDays(5)->toDateString();
        $promotion = Promotion::factory()->for($venue)->create([
            'organization_id' => $organization->getKey(),
            'resource_id' => $resource->getKey(),
            'title' => 'Original campaign title',
            'promotion_type' => PromotionType::SpecificSlots,
            'starts_on' => $date,
            'ends_on' => $date,
            'targets_specific_slots' => true,
        ]);
        $slot = PromotionSlot::factory()->for($promotion)->for($resource, 'resource')->create([
            'slot_date' => $date,
            'starts_at_time' => '09:00',
            'ends_at_time' => '10:00',
        ]);

        $this->get(route('marketplace.venues.show', [
            'venueSlug' => $venue->slug,
            'resource' => $resource->getKey(),
            'date' => $date,
            'utm_source' => 'facebook',
            'utm_medium' => 'social',
            'utm_campaign' => 'first-campaign',
        ]))->assertOk();

        $this->actingAs($player)->post(route('player.bookings.store', $venue->slug), [
            ...$this->holdData($resource, $date),
            'campaign' => $promotion->campaign_token,
        ])->assertRedirect();

        $booking = Booking::query()->where('player_user_id', $player->getKey())->firstOrFail();
        $snapshot = $booking->attribution()->firstOrFail();
        $this->assertSame(AcquisitionSource::Facebook, $snapshot->first_source);
        $this->assertSame(AcquisitionSource::MarketplacePromotion, $snapshot->last_source);
        $this->assertSame(AcquisitionSource::MarketplacePromotion, $snapshot->attributed_source);
        $this->assertSame($promotion->getKey(), $snapshot->promotion_id);
        $this->assertSame($promotion->campaign_token, $snapshot->promotion_campaign_token);
        $this->assertSame($slot->slot_token, $snapshot->promotion_slot_token);
        $this->assertSame('Original campaign title', $snapshot->promotion_title);
        $this->assertSame('last_touch_with_promotion_override_v1', $snapshot->rule_version);

        $promotion->update(['title' => 'Edited after booking']);
        $this->get('/courts?utm_source=instagram&utm_campaign=after-booking')->assertOk();

        $snapshot->refresh();
        $this->assertSame('Original campaign title', $snapshot->promotion_title);
        $this->assertSame(AcquisitionSource::Facebook, $snapshot->first_source);
        $this->assertSame(AcquisitionSource::MarketplacePromotion, $snapshot->attributed_source);
    }

    public function test_promotion_impressions_do_not_claim_a_touch_but_valid_campaign_clicks_do(): void
    {
        [, $venue, $resource] = $this->inventory('promotion-touch-attribution');
        $promotion = Promotion::factory()->for($venue)->create([
            'organization_id' => $venue->organization_id,
            'resource_id' => $resource->getKey(),
        ]);
        $session = app('session')->driver();
        $request = Request::create('/deals?utm_source=facebook&utm_campaign=discovery', 'GET');
        $request->setLaravelSession($session);
        $recorder = app(AnalyticsRecorder::class);

        $recorder->recordPromotionImpression($request, $promotion);
        $afterImpression = $this->capture('/deals', $session);

        $this->assertSame(AcquisitionSource::Facebook, $afterImpression['last_touch']['source']);

        $click = Request::create("/venues/{$venue->slug}?campaign={$promotion->campaign_token}", 'GET');
        $click->setLaravelSession($session);
        $recorder->recordPromotionClick($click, $promotion);
        $afterClick = $this->capture('/venues/'.$venue->slug, $session);

        $this->assertSame(AcquisitionSource::MarketplacePromotion, $afterClick['last_touch']['source']);
        $this->assertSame($promotion->campaign_token, $afterClick['last_touch']['campaign']);
    }

    public function test_direct_and_unknown_fallbacks_are_snapshotted_deterministically(): void
    {
        [, $venue, $resource] = $this->inventory('fallback-attribution');
        $directPlayer = User::factory()->create();
        $unknownPlayer = User::factory()->create();
        $date = now('Asia/Manila')->addDays(6)->toDateString();

        $this->actingAs($directPlayer)->post(route('player.bookings.store', $venue->slug), $this->holdData($resource, $date))
            ->assertRedirect();
        $direct = Booking::query()->where('player_user_id', $directPlayer->getKey())->firstOrFail();
        $this->assertSame(AcquisitionSource::Direct, $direct->attribution->attributed_source);

        auth()->logout();
        $this->get('/courts?acq_source=not-a-taxonomy-value')->assertOk();
        $this->actingAs($unknownPlayer)->post(route('player.bookings.store', $venue->slug), [
            ...$this->holdData($resource, $date, '11:00'),
        ])->assertRedirect();
        $unknown = Booking::query()->where('player_user_id', $unknownPlayer->getKey())->firstOrFail();
        $this->assertSame(AcquisitionSource::Unknown, $unknown->attribution->attributed_source);
    }

    public function test_owner_and_platform_channel_reports_are_tenant_safe_and_use_qualified_revenue(): void
    {
        [$organizationA, $venueA, $resourceA, $ownerA] = $this->inventory('channel-owner-a', true);
        [$organizationB, $venueB, $resourceB] = $this->inventory('channel-owner-b');
        $paid = $this->attributedBooking($organizationA, $venueA, $resourceA, AcquisitionSource::GoogleOrganic);
        $newCustomer = $paid->player_user_id;
        $this->attributedBooking($organizationA, $venueA, $resourceA, AcquisitionSource::QrCode, PaymentStatus::Refunded);
        $this->attributedBooking($organizationA, $venueA, $resourceA, AcquisitionSource::Referral, PaymentStatus::Paid, BookingStatus::Cancelled);
        $this->attributedBooking($organizationB, $venueB, $resourceB, AcquisitionSource::Facebook);
        $platform = User::factory()->platformAdmin()->create();

        $this->actingAs($ownerA)->get(route('owner.analytics'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('report.traffic_sources.0.source', AcquisitionSource::GoogleOrganic->value)
                ->where('report.traffic_sources.0.bookings', 1)
                ->where('report.traffic_sources.0.new_customers', 1)
                ->where('report.traffic_sources.0.revenue', '650.00')
                ->where('report.metrics.completed_bookings', 1)
                ->where('report.metrics.booking_revenue', '650.00')
                ->where('report.metrics.new_customers', 1)
                ->missing('report.traffic_sources.1'));

        $this->actingAs($platform)->get(route('platform.analytics'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('report.traffic_sources', 2)
                ->where('report.metrics.completed_bookings', 2));

        $this->assertNotNull($newCustomer);
    }

    public function test_attribution_snapshot_endpoint_does_not_exist_and_model_hides_no_raw_referrer_query(): void
    {
        $this->get('/acquisition-attributions/1')->assertNotFound();
        $session = app('session')->driver();
        $context = $this->capture(
            '/courts',
            $session,
            'https://search.example/path?email=player@example.com&secret=value',
        );

        $this->assertSame('search.example', $context['referrer_host']);
        $this->assertStringNotContainsString('email', json_encode($context, JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString('secret', json_encode($context, JSON_THROW_ON_ERROR));
    }

    public function test_booking_attribution_snapshot_rejects_direct_mutation(): void
    {
        [$organization, $venue, $resource] = $this->inventory('immutable-attribution');
        $booking = $this->attributedBooking(
            $organization,
            $venue,
            $resource,
            AcquisitionSource::GoogleMaps,
        );

        $this->expectException(\LogicException::class);
        $booking->attribution->update([
            'attributed_source' => AcquisitionSource::Direct,
        ]);
    }

    /** @return array{Organization, Venue, CourtResource, User|null} */
    private function inventory(string $slug, bool $withOwner = false): array
    {
        $organization = Organization::factory()->create(['timezone' => 'Asia/Manila']);
        $venue = Venue::factory()->for($organization)->published()->create([
            'slug' => $slug,
            'city' => 'Makati',
            'city_slug' => 'makati',
        ]);
        $sport = Sport::query()->firstOrCreate(
            ['slug' => 'badminton'],
            ['name' => 'Badminton', 'is_active' => true],
        );
        $resource = CourtResource::factory()->for($venue)->for($sport)->create([
            'base_hourly_rate' => '650.00',
            'booking_increment_minutes' => 60,
        ]);

        foreach (range(0, 6) as $day) {
            OperatingHour::factory()->for($venue)->create([
                'day_of_week' => $day,
                'opens_at' => '08:00',
                'closes_at' => '22:00',
            ]);
        }

        $owner = null;

        if ($withOwner) {
            $owner = User::factory()->create();
            Membership::factory()->owner()->for($owner)->for($organization)->create();
        }

        return [$organization, $venue, $resource, $owner];
    }

    /** @return array<string, mixed> */
    private function holdData(CourtResource $resource, string $date, string $start = '09:00'): array
    {
        return [
            'resource_id' => $resource->getKey(),
            'booking_date' => $date,
            'start_time' => $start,
            'duration_minutes' => 60,
            'customer_name' => 'Attributed Player',
            'terms' => '1',
        ];
    }

    private function attributedBooking(
        Organization $organization,
        Venue $venue,
        CourtResource $resource,
        AcquisitionSource $source,
        PaymentStatus $paymentStatus = PaymentStatus::Paid,
        BookingStatus $status = BookingStatus::Confirmed,
    ): Booking {
        $player = User::factory()->create();
        $booking = Booking::factory()->for($resource, 'resource')->create([
            'organization_id' => $organization->getKey(),
            'venue_id' => $venue->getKey(),
            'player_user_id' => $player->getKey(),
            'source' => BookingSource::Marketplace,
            'status' => $status,
            'payment_status' => $paymentStatus,
            'total_amount' => '650.00',
        ]);
        BookingAttribution::factory()->for($booking)->create([
            'organization_id' => $organization->getKey(),
            'venue_id' => $venue->getKey(),
            'first_source' => $source,
            'last_source' => $source,
            'attributed_source' => $source,
        ]);

        return $booking;
    }

    /** @return array<string, mixed> */
    private function capture(string $uri, mixed $session, ?string $referer = null): array
    {
        $server = $referer === null ? [] : ['HTTP_REFERER' => $referer];
        $request = Request::create($uri, 'GET', [], [], [], $server);
        $request->setLaravelSession($session);

        return app(TrafficAttribution::class)->current($request);
    }
}
