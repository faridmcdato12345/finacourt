<?php

namespace Tests\Feature;

use App\CustomerReactivation\CustomerClassifier;
use App\CustomerReactivation\ReactivationReport;
use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\CustomerLifecycle;
use App\Enums\PaymentStatus;
use App\Enums\ReactivationCampaignStatus;
use App\Enums\ReactivationSegment;
use App\Models\Booking;
use App\Models\CourtResource;
use App\Models\MarketingPreference;
use App\Models\Membership;
use App\Models\OperatingHour;
use App\Models\Organization;
use App\Models\ReactivationCampaign;
use App\Models\Sport;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CustomerReactivationTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_classification_is_deterministic_and_tenant_scoped(): void
    {
        [$organization, $venue, $resource] = $this->inventory('classification');
        [$otherOrganization, $otherVenue, $otherResource] = $this->inventory('classification-other');
        $new = User::factory()->create();
        $returning = User::factory()->create();
        $inactive = User::factory()->create();
        $onlyOtherTenant = User::factory()->create();

        $this->completedBooking($organization, $venue, $resource, $new, now()->subDays(5));
        $this->completedBooking($organization, $venue, $resource, $returning, now()->subDays(12));
        $this->completedBooking($organization, $venue, $resource, $returning, now()->subDays(3));
        $this->completedBooking($organization, $venue, $resource, $inactive, now()->subDays(61));
        $this->completedBooking($otherOrganization, $otherVenue, $otherResource, $onlyOtherTenant, now()->subDays(2));

        $classifier = app(CustomerClassifier::class);

        $this->assertSame(CustomerLifecycle::New, $classifier->classify($organization, $new));
        $this->assertSame(CustomerLifecycle::Returning, $classifier->classify($organization, $returning));
        $this->assertSame(CustomerLifecycle::Inactive, $classifier->classify($organization, $inactive));
        $this->assertNull($classifier->classify($organization, $onlyOtherTenant));
    }

    public function test_owner_segments_include_only_prior_customers_of_the_active_tenant(): void
    {
        [$organization, $venue, $resource, $owner, $sport] = $this->inventory('segments');
        [$otherOrganization, $otherVenue, $otherResource] = $this->inventory('segments-other');
        $customer = User::factory()->create();
        $unrelated = User::factory()->create();
        $this->completedBooking($organization, $venue, $resource, $customer, now()->subDays(40));
        $this->completedBooking($otherOrganization, $otherVenue, $otherResource, $unrelated, now()->subDays(40));

        $this->actingAs($owner)->get(route('owner.reactivation.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Owner/Reactivation/Index')
                ->where('segments.inactive_30', 1)
                ->where('segments.inactive_60', 0)
                ->where('segments.sports.0.id', $sport->getKey()));
    }

    public function test_explicit_opt_in_controls_delivery_and_unrelated_players_are_never_targeted(): void
    {
        [$organization, $venue, $resource, $owner, $sport] = $this->inventory('consent');
        [$otherOrganization, $otherVenue, $otherResource] = $this->inventory('consent-other');
        $optedIn = User::factory()->create();
        $optedOut = User::factory()->create();
        $unrelated = User::factory()->create();
        $this->completedBooking($organization, $venue, $resource, $optedIn, now()->subDays(45));
        $this->completedBooking($organization, $venue, $resource, $optedOut, now()->subDays(45));
        $this->completedBooking($otherOrganization, $otherVenue, $otherResource, $unrelated, now()->subDays(45));
        MarketingPreference::factory()->optedIn()->for($optedIn)->create();
        MarketingPreference::factory()->optedOut()->for($optedOut)->create();
        MarketingPreference::factory()->optedIn()->for($unrelated)->create();
        $campaign = $this->campaign($organization, $venue, $owner, $sport);

        $this->actingAs($owner)->post(route('owner.reactivation.send', $campaign))->assertRedirect();

        $this->assertSame(ReactivationCampaignStatus::Sent, $campaign->refresh()->status);
        $this->assertSame(2, $campaign->audience_count);
        $this->assertSame(1, $campaign->sent_count);
        $this->assertSame(1, $campaign->suppressed_count);
        $this->assertSame(1, $optedIn->notifications()->where('data->kind', 'customer_reactivation')->count());
        $this->assertSame(0, $optedOut->notifications()->count());
        $this->assertSame(0, $unrelated->notifications()->count());
        $this->assertDatabaseMissing('reactivation_campaign_recipients', [
            'reactivation_campaign_id' => $campaign->getKey(),
            'user_id' => $unrelated->getKey(),
        ]);
    }

    public function test_frequency_cooldown_suppresses_repeat_contact(): void
    {
        [$organization, $venue, $resource, $owner, $sport] = $this->inventory('cooldown');
        $customer = User::factory()->create();
        $this->completedBooking($organization, $venue, $resource, $customer, now()->subDays(45));
        MarketingPreference::factory()->optedIn()->for($customer)->create();
        $first = $this->campaign($organization, $venue, $owner, $sport, 'First comeback');
        $second = $this->campaign($organization, $venue, $owner, $sport, 'Second comeback');

        $this->actingAs($owner)->post(route('owner.reactivation.send', $first))->assertRedirect();
        $this->actingAs($owner)->post(route('owner.reactivation.send', $second))->assertRedirect();

        $this->assertSame(1, $customer->notifications()->where('data->kind', 'customer_reactivation')->count());
        $this->assertSame(1, $second->refresh()->suppressed_count);
        $this->assertDatabaseHas('reactivation_campaign_recipients', [
            'reactivation_campaign_id' => $second->getKey(),
            'user_id' => $customer->getKey(),
            'suppression_reason' => 'frequency_cooldown',
        ]);
    }

    public function test_owner_cannot_view_or_send_another_tenants_campaign(): void
    {
        [, , , $ownerA] = $this->inventory('tenant-a');
        [$organizationB, $venueB, , $ownerB, $sportB] = $this->inventory('tenant-b');
        $campaignB = $this->campaign($organizationB, $venueB, $ownerB, $sportB);

        $this->actingAs($ownerA)->get(route('owner.reactivation.show', $campaignB))->assertNotFound();
        $this->actingAs($ownerA)->post(route('owner.reactivation.send', $campaignB))->assertNotFound();
    }

    public function test_validated_campaign_click_is_snapshotted_and_reported_without_changing_booking_value(): void
    {
        [$organization, $venue, $resource, $owner, $sport] = $this->inventory('attribution');
        $player = User::factory()->create();
        $this->completedBooking($organization, $venue, $resource, $player, now()->subDays(45));
        MarketingPreference::factory()->optedIn()->for($player)->create();
        $campaign = $this->campaign($organization, $venue, $owner, $sport);
        $this->actingAs($owner)->post(route('owner.reactivation.send', $campaign))->assertRedirect();
        $recipient = $campaign->recipients()->where('user_id', $player->getKey())->firstOrFail();

        $this->actingAs($player)->get(route('player.reactivation.click', $recipient->click_token))->assertRedirect();
        $date = now($organization->timezone)->addDays(7)->toDateString();
        $this->actingAs($player)->post(route('player.bookings.store', $venue->slug), [
            'resource_id' => $resource->getKey(),
            'booking_date' => $date,
            'start_time' => '10:00',
            'duration_minutes' => 60,
            'customer_name' => $player->name,
            'customer_phone' => '09170000000',
            'terms' => '1',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $booking = Booking::query()->where('player_user_id', $player->getKey())->latest('id')->firstOrFail();
        $snapshot = $booking->attribution()->firstOrFail();
        $this->assertSame('customer_reactivation', $snapshot->attributed_source->value);
        $this->assertSame($campaign->getKey(), $snapshot->reactivation_campaign_id);
        $this->assertSame($campaign->campaign_token, $snapshot->reactivation_campaign_token);
        $this->assertSame($campaign->title, $snapshot->reactivation_campaign_title);
        $this->assertSame('1000.00', $booking->total_amount);

        $booking->update([
            'status' => BookingStatus::Confirmed,
            'payment_status' => PaymentStatus::Paid,
            'expires_at' => null,
        ]);
        $report = app(ReactivationReport::class)->forOrganization($organization);
        $this->assertSame(1, $report['resulting_bookings']);
        $this->assertSame('1000.00', $report['resulting_revenue']);
        $this->assertSame(1, $report['reactivated_customers']);
    }

    public function test_cancelled_and_refunded_bookings_do_not_count_as_campaign_revenue(): void
    {
        [$organization, $venue, $resource, $owner, $sport] = $this->inventory('revenue');
        $player = User::factory()->create();
        $campaign = $this->campaign($organization, $venue, $owner, $sport);

        foreach ([
            [BookingStatus::Cancelled, PaymentStatus::Paid],
            [BookingStatus::Confirmed, PaymentStatus::Refunded],
        ] as [$status, $payment]) {
            $booking = Booking::factory()->for($resource, 'resource')->create([
                'organization_id' => $organization->getKey(),
                'venue_id' => $venue->getKey(),
                'player_user_id' => $player->getKey(),
                'status' => $status,
                'source' => BookingSource::Marketplace,
                'payment_status' => $payment,
                'start_at' => now()->addDay(),
                'end_at' => now()->addDay()->addHour(),
            ]);
            $booking->attribution()->create([
                ...$this->attributionAttributes($organization, $venue),
                'reactivation_campaign_id' => $campaign->getKey(),
                'reactivation_campaign_token' => $campaign->campaign_token,
                'reactivation_campaign_title' => $campaign->title,
            ]);
        }

        $report = app(ReactivationReport::class)->forOrganization($organization);
        $this->assertSame(0, $report['resulting_bookings']);
        $this->assertSame('0.00', $report['resulting_revenue']);
    }

    public function test_player_can_explicitly_opt_in_and_unsubscribe_without_affecting_booking_notices(): void
    {
        $player = User::factory()->create();

        $this->actingAs($player)->put(route('player.preferences.update'), [
            'marketing_opt_in' => '1',
            'in_app_marketing_enabled' => '1',
        ])->assertRedirect();
        $this->assertTrue($player->marketingPreference()->firstOrFail()->canReceiveInAppMarketing());

        $this->actingAs($player)->put(route('player.preferences.update'), [])->assertRedirect();
        $preference = $player->marketingPreference()->firstOrFail();
        $this->assertFalse($preference->marketing_opt_in);
        $this->assertNotNull($preference->unsubscribed_at);
    }

    /** @return array{Organization, Venue, CourtResource, User, Sport} */
    private function inventory(string $slug): array
    {
        $organization = Organization::factory()->create(['timezone' => 'Asia/Manila']);
        $owner = User::factory()->create();
        Membership::factory()->owner()->for($owner)->for($organization)->create();
        $sport = Sport::factory()->create(['name' => "Sport {$slug}", 'slug' => $slug, 'is_active' => true]);
        $venue = Venue::factory()->published()->for($organization)->create([
            'slug' => $slug,
            'city' => 'Makati',
            'city_slug' => 'makati',
        ]);
        $resource = CourtResource::factory()->for($venue)->for($sport)->create([
            'base_hourly_rate' => 1000,
            'booking_increment_minutes' => 60,
            'is_active' => true,
        ]);
        foreach (range(0, 6) as $weekday) {
            OperatingHour::factory()->for($venue)->create([
                'day_of_week' => $weekday,
                'opens_at' => '08:00',
                'closes_at' => '22:00',
                'is_closed' => false,
            ]);
        }

        return [$organization, $venue, $resource, $owner, $sport];
    }

    private function completedBooking(
        Organization $organization,
        Venue $venue,
        CourtResource $resource,
        User $player,
        mixed $endedAt,
    ): Booking {
        return Booking::factory()->for($resource, 'resource')->create([
            'organization_id' => $organization->getKey(),
            'venue_id' => $venue->getKey(),
            'player_user_id' => $player->getKey(),
            'status' => BookingStatus::Confirmed,
            'source' => BookingSource::Marketplace,
            'payment_status' => PaymentStatus::Paid,
            'start_at' => $endedAt->copy()->subHour(),
            'end_at' => $endedAt,
        ]);
    }

    private function campaign(
        Organization $organization,
        Venue $venue,
        User $owner,
        Sport $sport,
        string $title = 'Come back and play',
    ): ReactivationCampaign {
        return ReactivationCampaign::factory()->for($organization)->for($venue)->for($owner, 'creator')->create([
            'sport_id' => $sport->getKey(),
            'title' => $title,
            'segment' => ReactivationSegment::Inactive30,
            'status' => ReactivationCampaignStatus::Draft,
        ]);
    }

    /** @return array<string, mixed> */
    private function attributionAttributes(Organization $organization, Venue $venue): array
    {
        $now = now('UTC');

        return [
            'organization_id' => $organization->getKey(),
            'venue_id' => $venue->getKey(),
            'first_source' => 'customer_reactivation',
            'first_seen_at' => $now,
            'last_source' => 'customer_reactivation',
            'last_seen_at' => $now,
            'attributed_source' => 'customer_reactivation',
            'attributed_at' => $now,
            'rule_version' => config('attribution.rule_version'),
        ];
    }
}
