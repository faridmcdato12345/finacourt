<?php

namespace Tests\Feature;

use App\Analytics\AnalyticsPeriod;
use App\Analytics\ExternalBookingTrafficReport;
use App\Enums\AcquisitionSource;
use App\Enums\AnalyticsEventType;
use App\Enums\VisibilityLinkDestination;
use App\Models\AnalyticsEvent;
use App\Models\Booking;
use App\Models\ExternalBookingDestination;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use App\Models\Venue;
use App\Models\VisibilityLink;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BookingLinksTest extends TestCase
{
    use RefreshDatabase;

    public function test_booking_links_page_and_destination_management_are_tenant_scoped(): void
    {
        [$ownerA, $venueA] = $this->inventory('booking-links-a');
        [$ownerB, $venueB] = $this->inventory('booking-links-b');

        $this->actingAs($ownerA)->get(route('owner.booking-links.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Owner/BookingLinks/Index')
                ->has('venues', 1)
                ->where('venues.0.id', $venueA->getKey())
                ->where('selectedVenue.id', $venueA->getKey())
                ->where('destination', null)
                ->where('performance.metrics.total_clicks', 0)
                ->missing('venues.1'));

        $this->actingAs($ownerA)->put(
            route('owner.venues.external-booking-destination.update', $venueA),
            [
                'destination_url' => '  https://Booking.Example/venues/demo?court=1  ',
                'provider_name' => ' Current Platform ',
                'is_active' => true,
            ],
        )->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('external_booking_destinations', [
            'organization_id' => $venueA->organization_id,
            'venue_id' => $venueA->getKey(),
            'provider_name' => 'Current Platform',
            'destination_url' => 'https://booking.example/venues/demo?court=1',
            'is_active' => true,
        ]);

        $this->actingAs($ownerA)->put(
            route('owner.venues.external-booking-destination.update', $venueB),
            [
                'destination_url' => 'https://other.example/book',
                'provider_name' => null,
                'is_active' => true,
            ],
        )->assertForbidden();
        $this->assertFalse($venueB->externalBookingDestination()->exists());
        $this->assertNotSame($ownerA->getKey(), $ownerB->getKey());
    }

    public function test_destination_rejects_unsafe_malformed_and_self_referencing_urls(): void
    {
        [$owner, $venue] = $this->inventory('unsafe-booking-link');
        config(['app.url' => 'https://finacourt.asia']);
        $unsafe = [
            'javascript:alert(1)',
            'data:text/html,bad',
            'file:///tmp/secret',
            'http://booking.example/insecure',
            'https://localhost/book',
            'https://127.0.0.1/book',
            'https://user:secret@booking.example/book',
            'https://finacourt.asia/go/loop',
            'not-a-url',
        ];

        foreach ($unsafe as $url) {
            $this->actingAs($owner)->put(
                route('owner.venues.external-booking-destination.update', $venue),
                [
                    'destination_url' => $url,
                    'provider_name' => null,
                    'is_active' => true,
                ],
            )->assertSessionHasErrors('destination_url');
        }

        $this->assertDatabaseCount('external_booking_destinations', 0);
    }

    public function test_owner_can_create_unique_default_and_custom_tracking_links(): void
    {
        [$owner, $venue] = $this->inventory('create-booking-links');
        [$otherOwner, $otherVenue] = $this->inventory('other-booking-links');
        $this->saveDestination($owner, $venue);

        $this->actingAs($owner)->post(route('owner.venues.booking-links.store', $venue), [
            'source' => AcquisitionSource::Facebook->value,
            'label' => 'Facebook',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($owner)->post(route('owner.venues.booking-links.store', $venue), [
            'source' => AcquisitionSource::Facebook->value,
            'label' => 'Weekend Facebook Promo',
            'campaign' => 'weekend-promo-september',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($owner)->post(route('owner.venues.booking-links.store', $venue), [
            'source' => AcquisitionSource::QrCode->value,
            'label' => 'Front desk QR',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $links = $venue->visibilityLinks()
            ->where('destination', VisibilityLinkDestination::ExternalBooking)
            ->get();

        $this->assertCount(3, $links);
        $this->assertCount(3, $links->pluck('token')->unique());
        $this->assertTrue($links->every(fn (VisibilityLink $link) => strlen($link->token) === 26));
        $this->assertSame(2, $links->where('acquisition_source', AcquisitionSource::Facebook)->count());
        $this->assertNotNull($links->firstWhere('campaign', 'weekend-promo-september'));

        $this->actingAs($otherOwner)->patch(route('owner.booking-links.update', $links->first()), [
            'label' => 'Cross-tenant rename',
        ])->assertForbidden();
        $this->actingAs($otherOwner)->delete(route('owner.booking-links.destroy', $links->first()))
            ->assertForbidden();

        $this->actingAs($owner)->post(route('owner.venues.booking-links.store', $otherVenue), [
            'source' => AcquisitionSource::Instagram->value,
            'label' => 'Instagram',
        ])->assertForbidden();
        $this->actingAs($owner)->post(route('owner.venues.visibility-links.store', $venue), [
            'destination' => VisibilityLinkDestination::ExternalBooking->value,
            'source' => AcquisitionSource::Facebook->value,
        ])->assertSessionHasErrors('destination');
    }

    public function test_external_redirect_records_clicks_and_unique_visitors_without_creating_booking_attribution(): void
    {
        [$owner, $venue] = $this->inventory('external-redirect');
        $this->saveDestination($owner, $venue, 'https://booking.example/original');
        $link = $this->createLink($owner, $venue, AcquisitionSource::Facebook);

        $this->withHeader('User-Agent', 'Mozilla/5.0 FinACourt test browser')
            ->withHeader('Referer', 'https://facebook.com/community-post')
            ->get(route('visibility-links.visit', $link->token))
            ->assertRedirect('https://booking.example/original')
            ->assertStatus(302)
            ->assertSessionMissing('analytics.acquisition_context');
        $this->get(route('visibility-links.visit', $link->token))
            ->assertRedirect('https://booking.example/original');

        $events = AnalyticsEvent::query()
            ->where('event_type', AnalyticsEventType::ExternalBookingLinkClick)
            ->get();
        $this->assertCount(2, $events);
        $this->assertSame(1, $events->pluck('visitor_hash')->unique()->count());
        $this->assertTrue($events->every(fn (AnalyticsEvent $event) => $event->visibility_link_id === $link->getKey()));
        $this->assertSame('facebook.com', $events->first()->metadata['referrer_host']);
        $this->assertSame(2, $link->fresh()->visits_count);
        $this->assertSame(0, Booking::query()->count());

        $this->withHeader('User-Agent', 'Googlebot/2.1')
            ->get(route('visibility-links.visit', $link->token))
            ->assertRedirect('https://booking.example/original');
        $this->assertSame(2, AnalyticsEvent::query()
            ->where('event_type', AnalyticsEventType::ExternalBookingLinkClick)
            ->count());
        $this->assertSame(2, $link->fresh()->visits_count);
    }

    public function test_destination_changes_keep_links_and_history_while_disabled_or_deleted_links_do_not_redirect(): void
    {
        [$owner, $venue] = $this->inventory('destination-change');
        $destination = $this->saveDestination($owner, $venue, 'https://platform-a.example/book');
        $link = $this->createLink($owner, $venue, AcquisitionSource::SharedLink);
        $this->get(route('visibility-links.visit', $link->token))
            ->assertRedirect('https://platform-a.example/book');

        $this->saveDestination($owner, $venue, 'https://platform-b.example/reserve');
        $this->get(route('visibility-links.visit', $link->token))
            ->assertRedirect('https://platform-b.example/reserve');
        $this->assertSame($destination->getKey(), $link->fresh()->external_booking_destination_id);
        $this->assertSame(2, AnalyticsEvent::query()
            ->where('visibility_link_id', $link->getKey())
            ->count());

        $this->actingAs($owner)->patch(route('owner.booking-links.update', $link), [
            'is_active' => false,
        ])->assertRedirect();
        $this->get(route('visibility-links.visit', $link->token))->assertNotFound();
        $this->assertSame(2, AnalyticsEvent::query()->where('visibility_link_id', $link->getKey())->count());

        $this->actingAs($owner)->patch(route('owner.booking-links.update', $link), [
            'is_active' => true,
        ])->assertRedirect();
        $this->saveDestination($owner, $venue, 'https://platform-b.example/reserve', false);
        $this->get(route('visibility-links.visit', $link->token))->assertStatus(410);

        $this->actingAs($owner)->delete(route('owner.booking-links.destroy', $link))->assertRedirect();
        $this->assertSoftDeleted('visibility_links', ['id' => $link->getKey()]);
        $this->assertSame(2, AnalyticsEvent::query()->where('visibility_link_id', $link->getKey())->count());
        $this->get(route('visibility-links.visit', $link->token))->assertNotFound();
    }

    public function test_external_traffic_reporting_groups_sources_dates_and_remains_separate_in_main_analytics(): void
    {
        CarbonImmutable::setTestNow('2026-09-12 12:00:00');
        [$owner, $venue] = $this->inventory('external-report');
        $this->saveDestination($owner, $venue);
        $facebook = $this->createLink($owner, $venue, AcquisitionSource::Facebook);
        $google = $this->createLink($owner, $venue, AcquisitionSource::GoogleMaps);

        $this->withSession(['analytics.visitor_token' => str_repeat('a', 64)])
            ->get(route('visibility-links.visit', $facebook->token))->assertRedirect();
        $this->get(route('visibility-links.visit', $facebook->token))->assertRedirect();
        $this->withSession(['analytics.visitor_token' => str_repeat('b', 64)])
            ->get(route('visibility-links.visit', $google->token))->assertRedirect();
        AnalyticsEvent::factory()->for($venue)->create([
            'organization_id' => $venue->organization_id,
            'visibility_link_id' => $facebook->getKey(),
            'event_type' => AnalyticsEventType::ExternalBookingLinkClick,
            'traffic_source' => AcquisitionSource::Facebook->value,
            'occurred_at' => '2026-08-01 00:00:00',
        ]);

        $period = AnalyticsPeriod::fromFilters([
            'from' => '2026-09-01',
            'to' => '2026-09-12',
        ], $venue->organization->timezone);
        $report = app(ExternalBookingTrafficReport::class)
            ->generate($period, $venue->organization, $venue);

        $this->assertSame(3, $report['metrics']['total_clicks']);
        $this->assertSame(2, $report['metrics']['unique_visitors']);
        $this->assertSame('Facebook', $report['top_source']['label']);
        $this->assertSame(2, $report['top_source']['clicks']);
        $this->assertFalse($report['booking_conversion_available']);

        $this->actingAs($owner)->get(route('owner.analytics', [
            'from' => '2026-09-01',
            'to' => '2026-09-12',
            'venue' => $venue->getKey(),
        ]))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('externalTraffic.metrics.total_clicks', 3)
            ->where('externalTraffic.metrics.unique_visitors', 2)
            ->where('externalTraffic.booking_conversion_available', false)
            ->where('report.metrics.completed_bookings', 0)
            ->has('report.traffic_sources', 0));
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_qr_uses_the_tracking_url_and_records_only_when_the_tracking_link_is_opened(): void
    {
        [$owner, $venue] = $this->inventory('external-qr');
        $this->saveDestination($owner, $venue);
        $link = $this->createLink($owner, $venue, AcquisitionSource::QrCode);

        $this->get(route('visibility-links.qr', $link->token))
            ->assertOk()
            ->assertHeader('content-type', 'image/svg+xml')
            ->assertSee('<svg', false);
        $this->assertDatabaseCount('analytics_events', 0);

        $this->get(route('visibility-links.visit', $link->token))->assertRedirect();
        $this->assertDatabaseHas('analytics_events', [
            'visibility_link_id' => $link->getKey(),
            'event_type' => AnalyticsEventType::ExternalBookingLinkClick->value,
            'traffic_source' => AcquisitionSource::QrCode->value,
        ]);
    }

    /** @return array{User, Venue} */
    private function inventory(string $slug): array
    {
        $organization = Organization::factory()->create();
        $owner = User::factory()->create();
        Membership::factory()->owner()->for($owner)->for($organization)->create();
        $venue = Venue::factory()->for($organization)->create(['slug' => $slug]);

        return [$owner, $venue];
    }

    private function saveDestination(
        User $owner,
        Venue $venue,
        string $url = 'https://booking.example/venue',
        bool $active = true,
    ): ExternalBookingDestination {
        $this->actingAs($owner)->put(
            route('owner.venues.external-booking-destination.update', $venue),
            [
                'destination_url' => $url,
                'provider_name' => 'External Platform',
                'is_active' => $active,
            ],
        )->assertRedirect()->assertSessionHasNoErrors();

        return $venue->externalBookingDestination()->firstOrFail();
    }

    private function createLink(
        User $owner,
        Venue $venue,
        AcquisitionSource $source,
    ): VisibilityLink {
        $this->actingAs($owner)->post(route('owner.venues.booking-links.store', $venue), [
            'source' => $source->value,
            'label' => $source->label(),
        ])->assertRedirect()->assertSessionHasNoErrors();

        return $venue->visibilityLinks()
            ->where('destination', VisibilityLinkDestination::ExternalBooking)
            ->where('acquisition_source', $source)
            ->firstOrFail();
    }
}
