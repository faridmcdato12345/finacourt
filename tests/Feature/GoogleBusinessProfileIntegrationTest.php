<?php

namespace Tests\Feature;

use App\Enums\GoogleBusinessProfileConnectionStatus;
use App\Google\BusinessProfile\Contracts\GoogleBusinessProfileClient;
use App\Google\BusinessProfile\GoogleBusinessProfileConnectionManager;
use App\Google\BusinessProfile\GoogleBusinessProfileException;
use App\Google\BusinessProfile\GoogleBusinessProfileHttpClient;
use App\Google\BusinessProfile\GoogleBusinessProfileMatcher;
use App\Google\BusinessProfile\GoogleOAuthStateManager;
use App\Google\BusinessProfile\GoogleOAuthTokens;
use App\Google\BusinessProfile\NullGoogleBusinessProfileClient;
use App\Jobs\DiscoverGoogleBusinessProfiles;
use App\Models\CourtResource;
use App\Models\GoogleBusinessProfileAudit;
use App\Models\GoogleBusinessProfileConnection;
use App\Models\GoogleBusinessProfileOAuthState;
use App\Models\Membership;
use App\Models\OperatingHour;
use App\Models\Organization;
use App\Models\Sport;
use App\Models\User;
use App\Models\Venue;
use App\Visibility\VenuePublicUrl;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class GoogleBusinessProfileIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_edit_shows_google_readiness_and_the_real_generated_public_url(): void
    {
        [$owner, $venue] = $this->inventory('google-readiness');
        $this->fakeGoogle();

        $this->actingAs($owner)->get(route('owner.venues.edit', $venue))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Owner/Venues/Edit')
                ->where('googleBusinessProfile.available', true)
                ->where('googleBusinessProfile.public_page_ready', true)
                ->where('googleBusinessProfile.public_url', route('marketplace.venues.show', [
                    'venueSlug' => $venue->slug,
                ]))
                ->where('googleBusinessProfile.status', 'not_connected')
                ->where('googleBusinessProfile.readiness.score', 100));
    }

    public function test_ordinary_venue_creation_never_contacts_google_or_requires_a_booking_url(): void
    {
        $client = \Mockery::mock(GoogleBusinessProfileClient::class);
        $this->app->instance(GoogleBusinessProfileClient::class, $client);
        $organization = Organization::factory()->create();
        $owner = User::factory()->create();
        Membership::factory()->owner()->for($owner)->for($organization)->create();
        $sport = Sport::factory()->create();

        $this->actingAs($owner)->get(route('owner.venues.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Owner/Venues/Create')
                ->missing('googleBusinessProfile'));

        $this->actingAs($owner)->post(route('owner.venues.store'), [
            'name' => 'No Google Required Courts',
            'slug' => null,
            'description' => 'Venue creation stays independent from optional Google services.',
            'address' => '100 Main Street',
            'city' => 'Marawi City',
            'province' => 'Lanao del Sur',
            'latitude' => '8.0000000',
            'longitude' => '124.2900000',
            'phone' => '+63 917 555 0101',
            'email' => 'venue@example.com',
            'website' => null,
            'is_published' => false,
            'sports' => [$sport->getKey()],
            'amenities' => [],
        ])->assertRedirect();

        $this->assertDatabaseHas('venues', ['slug' => 'no-google-required-courts']);
        $this->assertDatabaseCount('google_business_profile_connections', 0);
    }

    public function test_public_url_uses_the_current_environment_host_and_is_not_persisted(): void
    {
        [, $venue] = $this->inventory('environment-url');
        URL::forceRootUrl('https://staging.finacourt.example');
        URL::forceScheme('https');

        $url = app(VenuePublicUrl::class)->canonical($venue);

        $this->assertSame('https://staging.finacourt.example/venues/environment-url', $url);
        $this->assertDatabaseMissing('google_business_profile_connections', ['venue_id' => $venue->getKey()]);

        URL::forceRootUrl(null);
        URL::forceScheme(null);
    }

    public function test_oauth_start_uses_a_hashed_expiring_tenant_bound_state(): void
    {
        [$owner, $venue] = $this->inventory('google-state');
        $fake = $this->fakeGoogle();

        $response = $this->actingAs($owner)
            ->withHeader('X-Inertia', 'true')
            ->post(route('owner.venues.google-business-profile.connect', $venue));

        $response->assertStatus(409)->assertHeader('X-Inertia-Location');
        $this->assertNotNull($fake->lastState);
        $state = GoogleBusinessProfileOAuthState::query()->sole();
        $this->assertSame(hash('sha256', $fake->lastState), $state->state_hash);
        $this->assertNotSame($fake->lastState, $state->state_hash);
        $this->assertSame($venue->organization_id, $state->organization_id);
        $this->assertSame($owner->getKey(), $state->user_id);
        $this->assertTrue($state->expires_at->isFuture());
    }

    public function test_oauth_callback_queues_profile_discovery_after_storing_encrypted_tokens(): void
    {
        Queue::fake();
        [$owner, $venue] = $this->inventory('google-queued');
        $fake = $this->fakeGoogle();

        $this->actingAs($owner)->withHeader('X-Inertia', 'true')
            ->post(route('owner.venues.google-business-profile.connect', $venue));
        $response = $this->withoutHeader('X-Inertia')->actingAs($owner)->get(route('owner.google-business-profile.callback', [
            'state' => $fake->lastState,
            'code' => 'one-time-code',
        ]));

        $response->assertRedirect(route('owner.venues.edit', $venue));
        $connection = GoogleBusinessProfileConnection::query()->sole();
        $this->assertSame(GoogleBusinessProfileConnectionStatus::PendingDiscovery, $connection->status);
        $this->assertNotNull($connection->discovery_generation);
        $this->assertSame('access-secret', $connection->access_token);
        $this->assertNotSame('access-secret', DB::table('google_business_profile_connections')->value('access_token'));
        Queue::assertPushed(DiscoverGoogleBusinessProfiles::class, fn (DiscoverGoogleBusinessProfiles $job): bool => $job->connectionId === $connection->getKey()
            && $job->organizationId === $venue->organization_id
            && $job->generation === $connection->discovery_generation
            && $job->queue === 'default');
        $this->assertTrue(GoogleBusinessProfileAudit::query()->where('event_type', 'discovery_queued')->exists());
    }

    public function test_rate_limited_discovery_uses_backoff_then_finishes_in_a_safe_recoverable_state(): void
    {
        [$owner, $venue] = $this->inventory('google-rate-limited');
        $fake = new RateLimitedGoogleBusinessProfileClient;
        $this->app->instance(GoogleBusinessProfileClient::class, $fake);
        $connection = app(GoogleBusinessProfileConnectionManager::class)
            ->authorize($venue, $owner, 'one-time-code');

        $firstAttempt = new DiscoverGoogleBusinessProfiles(
            $connection->getKey(),
            $connection->organization_id,
            $connection->discovery_generation,
        );

        try {
            app()->call([$firstAttempt, 'handle']);
            $this->fail('A transient Google quota response should be released for retry.');
        } catch (GoogleBusinessProfileException $exception) {
            $this->assertSame('RESOURCE_EXHAUSTED', $exception->errorCode);
        }

        $connection->refresh();
        $this->assertSame(GoogleBusinessProfileConnectionStatus::PendingDiscovery, $connection->status);
        $this->assertSame('Google is busy. FinACourt will check again automatically.', $connection->last_error_message);
        $this->assertSame([60, 300, 900, 1800], $firstAttempt->backoff());
        $this->assertTrue(GoogleBusinessProfileAudit::query()->where('event_type', 'discovery_retry_scheduled')->exists());

        $lastAttempt = new class($connection->getKey(), $connection->organization_id, $connection->discovery_generation) extends DiscoverGoogleBusinessProfiles
        {
            public function attempts(): int
            {
                return $this->tries;
            }
        };
        app()->call([$lastAttempt, 'handle']);

        $connection->refresh();
        $this->assertSame(GoogleBusinessProfileConnectionStatus::ReconnectRequired, $connection->status);
        $this->assertNull($connection->discovery_generation);
        $this->assertSame('RESOURCE_EXHAUSTED', $connection->last_error_code);
        $this->assertTrue(GoogleBusinessProfileAudit::query()->where('event_type', 'discovery_failed')->exists());
    }

    public function test_queued_discovery_cannot_cross_tenants_or_apply_a_stale_generation(): void
    {
        [$owner, $venue] = $this->inventory('google-job-tenant');
        [, $otherVenue] = $this->inventory('google-job-other-tenant');
        $fake = $this->fakeGoogle([$this->location($venue, 'locations/tenant-safe')]);
        $connections = app(GoogleBusinessProfileConnectionManager::class);
        $connection = $connections->authorize($venue, $owner, 'one-time-code');
        $staleGeneration = $connection->discovery_generation;
        $connection = $connections->retry($venue, $owner);

        app()->call([(new DiscoverGoogleBusinessProfiles(
            $connection->getKey(),
            $otherVenue->organization_id,
            $connection->discovery_generation,
        )), 'handle']);
        app()->call([(new DiscoverGoogleBusinessProfiles(
            $connection->getKey(),
            $connection->organization_id,
            $staleGeneration,
        )), 'handle']);

        $this->assertSame(0, $fake->accountsCalls);
        $this->assertSame(GoogleBusinessProfileConnectionStatus::PendingDiscovery, $connection->fresh()->status);
    }

    public function test_owner_can_queue_a_retry_without_repeating_google_consent(): void
    {
        Queue::fake();
        [$owner, $venue] = $this->inventory('google-owner-retry');
        $connection = GoogleBusinessProfileConnection::query()->create([
            'organization_id' => $venue->organization_id,
            'venue_id' => $venue->getKey(),
            'authorized_by_user_id' => $owner->getKey(),
            'status' => GoogleBusinessProfileConnectionStatus::ReconnectRequired,
            'access_token' => 'saved-access-token',
            'refresh_token' => 'saved-refresh-token',
            'token_expires_at' => now('UTC')->addHour(),
            'last_error_code' => 'RESOURCE_EXHAUSTED',
        ]);

        $this->actingAs($owner)
            ->post(route('owner.venues.google-business-profile.retry', $venue))
            ->assertRedirect(route('owner.venues.edit', $venue));

        $connection->refresh();
        $this->assertSame(GoogleBusinessProfileConnectionStatus::PendingDiscovery, $connection->status);
        Queue::assertPushed(DiscoverGoogleBusinessProfiles::class, fn (DiscoverGoogleBusinessProfiles $job): bool => $job->connectionId === $connection->getKey()
            && $job->generation === $connection->discovery_generation);
    }

    public function test_tampered_or_expired_oauth_state_is_rejected_without_being_consumed(): void
    {
        [$owner, $venue] = $this->inventory('google-invalid-state');
        $fake = $this->fakeGoogle();
        $this->actingAs($owner)->withHeader('X-Inertia', 'true')
            ->post(route('owner.venues.google-business-profile.connect', $venue));
        $state = GoogleBusinessProfileOAuthState::query()->sole();

        $this->withoutHeader('X-Inertia')->actingAs($owner)
            ->from(route('owner.venues.edit', $venue))
            ->get(route('owner.google-business-profile.callback', [
                'state' => str_repeat('a', 64),
                'code' => 'code',
            ]))
            ->assertSessionHasErrors('google');
        $this->assertNull($state->fresh()->consumed_at);

        $state->forceFill(['expires_at' => now('UTC')->subMinute()])->save();
        $this->actingAs($owner)
            ->from(route('owner.venues.edit', $venue))
            ->get(route('owner.google-business-profile.callback', [
                'state' => $fake->lastState,
                'code' => 'code',
            ]))
            ->assertSessionHasErrors('google');
        $this->assertNull($state->fresh()->consumed_at);
        $this->assertDatabaseCount('google_business_profile_connections', 0);
    }

    public function test_owner_can_authorize_discover_confirm_and_disconnect_an_accessible_profile(): void
    {
        [$owner, $venue] = $this->inventory('google-connect');
        $fake = $this->fakeGoogle([
            $this->location($venue, 'locations/venue-100', 'ChIJ-finacourt-100'),
        ]);

        $this->actingAs($owner)
            ->withHeader('X-Inertia', 'true')
            ->post(route('owner.venues.google-business-profile.connect', $venue))
            ->assertStatus(409);

        $this->withoutHeader('X-Inertia')->actingAs($owner)->get(route('owner.google-business-profile.callback', [
            'state' => $fake->lastState,
            'code' => 'one-time-code',
        ]))->assertRedirect(route('owner.venues.edit', $venue));

        $connection = GoogleBusinessProfileConnection::query()->sole();
        $this->assertSame(GoogleBusinessProfileConnectionStatus::NeedsConfirmation, $connection->status);
        $this->assertCount(1, $connection->candidates);
        $this->assertSame('access-secret', $connection->access_token);
        $this->assertSame('refresh-secret', $connection->refresh_token);
        $this->assertNotSame('access-secret', DB::table('google_business_profile_connections')->value('access_token'));
        $this->assertNotSame('refresh-secret', DB::table('google_business_profile_connections')->value('refresh_token'));

        $candidateKey = $connection->candidates[0]['key'];
        $this->actingAs($owner)->post(route('owner.venues.google-business-profile.confirm', [
            'venue' => $venue,
            'candidateKey' => $candidateKey,
        ]))->assertRedirect(route('owner.venues.edit', $venue));

        $connection->refresh();
        $venue->refresh();
        $this->assertSame(GoogleBusinessProfileConnectionStatus::Connected, $connection->status);
        $this->assertSame('locations/venue-100', $connection->google_location_name);
        $this->assertNull($connection->candidates);
        $this->assertSame('ChIJ-finacourt-100', $venue->google_place_id);
        $this->assertSame('business_profile', $venue->google_place_id_source);

        $this->actingAs($owner)->get(route('owner.venues.edit', $venue))
            ->assertDontSee('access-secret')
            ->assertDontSee('refresh-secret')
            ->assertDontSee('locations/venue-100');

        $this->actingAs($owner)->delete(route('owner.venues.google-business-profile.disconnect', $venue))
            ->assertRedirect(route('owner.venues.edit', $venue));

        $connection->refresh();
        $this->assertSame(GoogleBusinessProfileConnectionStatus::Disconnected, $connection->status);
        $this->assertNull($connection->access_token);
        $this->assertNull($connection->google_location_name);
        $this->assertSame(['refresh-secret'], $fake->revokedTokens);
        $this->assertTrue(GoogleBusinessProfileAudit::query()->where('event_type', 'profile_connected')->exists());
        $this->assertTrue(GoogleBusinessProfileAudit::query()->where('event_type', 'profile_disconnected')->exists());
    }

    public function test_no_accessible_match_does_not_create_or_change_a_google_profile(): void
    {
        [$owner, $venue] = $this->inventory('google-no-match');
        $fake = $this->fakeGoogle([
            [
                'name' => 'locations/unrelated',
                'title' => 'Unrelated Bakery',
                'storefrontAddress' => ['addressLines' => ['1 Far Away Road'], 'locality' => 'Cebu'],
            ],
        ]);

        $this->actingAs($owner)->withHeader('X-Inertia', 'true')
            ->post(route('owner.venues.google-business-profile.connect', $venue));
        $this->withoutHeader('X-Inertia')->actingAs($owner)->get(route('owner.google-business-profile.callback', [
            'state' => $fake->lastState,
            'code' => 'code',
        ]))->assertRedirect();

        $connection = GoogleBusinessProfileConnection::query()->sole();
        $this->assertSame(GoogleBusinessProfileConnectionStatus::NoMatch, $connection->status);
        $this->assertSame('no_match', $connection->match_outcome);
        $this->assertSame(1, Venue::query()->count());
        $this->assertNull($venue->fresh()->google_place_id);
        $this->assertNull($connection->google_location_name);
    }

    public function test_a_google_location_cannot_be_connected_to_two_finacourt_venues(): void
    {
        [$owner, $venue] = $this->inventory('google-duplicate-one');
        [, $secondVenue] = $this->inventory('google-duplicate-two', $venue->organization, $owner);
        $fake = $this->fakeGoogle([$this->location($secondVenue, 'locations/shared')]);
        GoogleBusinessProfileConnection::query()->create([
            'organization_id' => $venue->organization_id,
            'venue_id' => $venue->getKey(),
            'status' => GoogleBusinessProfileConnectionStatus::Connected,
            'google_location_name' => 'locations/shared',
        ]);

        $this->actingAs($owner)->withHeader('X-Inertia', 'true')
            ->post(route('owner.venues.google-business-profile.connect', $secondVenue));
        $this->withoutHeader('X-Inertia')->actingAs($owner)->get(route('owner.google-business-profile.callback', [
            'state' => $fake->lastState,
            'code' => 'code',
        ]));
        $candidateKey = $secondVenue->googleBusinessProfileConnection()->firstOrFail()->candidates[0]['key'];

        $this->actingAs($owner)->from(route('owner.venues.edit', $secondVenue))
            ->post(route('owner.venues.google-business-profile.confirm', [
                'venue' => $secondVenue,
                'candidateKey' => $candidateKey,
            ]))
            ->assertSessionHasErrors('google');

        $this->assertSame('locations/shared', $venue->googleBusinessProfileConnection()->value('google_location_name'));
        $this->assertNull($secondVenue->googleBusinessProfileConnection()->value('google_location_name'));
    }

    public function test_other_tenant_cannot_view_or_connect_a_venue_google_profile(): void
    {
        [$owner, $venue] = $this->inventory('google-tenant-one');
        [$otherOwner] = $this->inventory('google-tenant-two');
        $this->fakeGoogle();

        $this->actingAs($otherOwner)->get(route('owner.venues.edit', $venue))->assertForbidden();
        $this->actingAs($otherOwner)->post(route('owner.venues.google-business-profile.connect', $venue))->assertForbidden();
        $this->actingAs($otherOwner)->post(route('owner.venues.google-business-profile.retry', $venue))->assertForbidden();
        $this->assertDatabaseCount('google_business_profile_oauth_states', 0);
        $this->assertNotSame($owner->getKey(), $otherOwner->getKey());
    }

    public function test_google_not_configured_never_blocks_venue_editing(): void
    {
        [$owner, $venue] = $this->inventory('google-disabled');
        $this->app->instance(GoogleBusinessProfileClient::class, new NullGoogleBusinessProfileClient);

        $this->actingAs($owner)->get(route('owner.venues.edit', $venue))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('googleBusinessProfile.available', false)
                ->where('googleBusinessProfile.status', 'unavailable'));

        $this->actingAs($owner)->post(route('owner.venues.google-business-profile.connect', $venue))
            ->assertRedirect();
        $this->assertDatabaseCount('google_business_profile_connections', 0);
    }

    public function test_matcher_reports_ambiguous_candidates_instead_of_guessing(): void
    {
        [, $venue] = $this->inventory('central-sports');
        $matcher = app(GoogleBusinessProfileMatcher::class);
        $result = $matcher->match($venue, [
            ['key' => 'a', 'title' => $venue->name, 'address' => 'Other address'],
            ['key' => 'b', 'title' => $venue->name, 'address' => 'Another address'],
        ]);

        $this->assertSame('ambiguous', $result['outcome']);
        $this->assertCount(2, $result['candidates']);
    }

    public function test_matcher_uses_an_existing_google_place_id_as_an_exact_signal(): void
    {
        [, $venue] = $this->inventory('exact-place');
        $venue->forceFill(['google_place_id' => 'ChIJ-exact'])->save();
        $result = app(GoogleBusinessProfileMatcher::class)->match($venue, [[
            'key' => 'exact',
            'title' => 'A differently formatted venue name',
            'address' => 'A differently formatted address',
            'place_id' => 'ChIJ-exact',
        ]]);

        $this->assertSame('exact', $result['outcome']);
        $this->assertSame(['Same Google place ID'], $result['candidates'][0]['signals']);
    }

    public function test_google_api_failure_is_contained_to_the_connection_panel(): void
    {
        [$owner, $venue] = $this->inventory('google-api-failure');
        $fake = new FailingGoogleBusinessProfileClient;
        $this->app->instance(GoogleBusinessProfileClient::class, $fake);

        $this->actingAs($owner)->withHeader('X-Inertia', 'true')
            ->post(route('owner.venues.google-business-profile.connect', $venue));
        $response = $this->withoutHeader('X-Inertia')->actingAs($owner)->get(route('owner.google-business-profile.callback', [
            'state' => $fake->lastState,
            'code' => 'code',
        ]));

        $response->assertRedirect(route('owner.venues.edit', $venue));
        $this->assertSame(
            GoogleBusinessProfileConnectionStatus::ReconnectRequired,
            $venue->googleBusinessProfileConnection()->firstOrFail()->status,
        );
        $this->assertTrue($venue->fresh()->is_published);
        $this->assertTrue(GoogleBusinessProfileAudit::query()->where('event_type', 'discovery_failed')->exists());
    }

    public function test_oauth_state_cannot_cross_the_active_tenant(): void
    {
        [$owner, $firstVenue] = $this->inventory('state-tenant-one');
        $secondOrganization = Organization::factory()->create();
        Membership::factory()->owner()->for($owner)->for($secondOrganization)->create();
        [, $secondVenue] = $this->inventory('state-tenant-two', $secondOrganization, $owner);
        $plainState = app(GoogleOAuthStateManager::class)->issue($secondVenue, $owner);

        $this->actingAs($owner)
            ->withSession(['tenant.organization_id' => $firstVenue->organization_id])
            ->from(route('owner.venues.edit', $firstVenue))
            ->get(route('owner.google-business-profile.callback', [
                'state' => $plainState,
                'code' => 'code',
            ]))
            ->assertSessionHasErrors('google');

        $this->assertNull(GoogleBusinessProfileOAuthState::query()->sole()->consumed_at);
        $this->assertDatabaseCount('google_business_profile_connections', 0);
    }

    public function test_platform_admin_can_inspect_safe_connection_health_without_tokens(): void
    {
        [, $venue] = $this->inventory('admin-visibility');
        GoogleBusinessProfileConnection::query()->create([
            'organization_id' => $venue->organization_id,
            'venue_id' => $venue->getKey(),
            'status' => GoogleBusinessProfileConnectionStatus::ReconnectRequired,
            'google_location_title' => 'FinACourt admin visibility',
            'access_token' => 'platform-hidden-access-token',
            'refresh_token' => 'platform-hidden-refresh-token',
            'last_error_code' => 'PERMISSION_DENIED',
            'last_error_message' => 'Google access needs attention.',
        ]);

        $admin = User::factory()->platformAdmin()->create();
        $response = $this->actingAs($admin)->get(route('platform.dashboard'));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Platform/Dashboard')
            ->where('googleBusinessProfiles.counts.reconnect_required', 1)
            ->where('googleBusinessProfiles.recent.0.venue', $venue->name)
            ->where('googleBusinessProfiles.recent.0.last_error_code', 'PERMISSION_DENIED')
            ->missing('googleBusinessProfiles.recent.0.access_token')
            ->missing('googleBusinessProfiles.recent.0.refresh_token')
            ->missing('googleBusinessProfiles.recent.0.google_location_name')
            ->missing('googleBusinessProfiles.recent.0.google_account_name'));
    }

    public function test_reconnect_keeps_an_existing_profile_link_until_a_replacement_is_confirmed(): void
    {
        [$owner, $venue] = $this->inventory('safe-reconnect');
        $existing = GoogleBusinessProfileConnection::query()->create([
            'organization_id' => $venue->organization_id,
            'venue_id' => $venue->getKey(),
            'status' => GoogleBusinessProfileConnectionStatus::Connected,
            'google_location_name' => 'locations/original',
            'google_location_title' => 'Original profile',
            'refresh_token' => 'original-refresh-token',
        ]);
        $fake = $this->fakeGoogle([
            $this->location($venue, 'locations/replacement', 'ChIJ-replacement'),
        ]);

        $this->actingAs($owner)->withHeader('X-Inertia', 'true')
            ->post(route('owner.venues.google-business-profile.connect', $venue));
        $this->withoutHeader('X-Inertia')->actingAs($owner)->get(route('owner.google-business-profile.callback', [
            'state' => $fake->lastState,
            'code' => 'replacement-code',
        ]))->assertRedirect(route('owner.venues.edit', $venue));

        $existing->refresh();
        $this->assertSame(GoogleBusinessProfileConnectionStatus::Connected, $existing->status);
        $this->assertSame('locations/original', $existing->google_location_name);
        $this->assertSame('Original profile', $existing->google_location_title);
        $this->assertCount(1, $existing->candidates);
    }

    public function test_http_adapter_uses_the_current_account_and_business_information_contracts(): void
    {
        $this->configureHttpClient();
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'http-access-token',
                'refresh_token' => 'http-refresh-token',
                'expires_in' => 3600,
                'scope' => 'https://www.googleapis.com/auth/business.manage',
            ]),
            'https://mybusinessaccountmanagement.googleapis.com/v1/accounts*' => Http::response([
                'accounts' => [['name' => 'accounts/123', 'accountName' => 'Managed venues']],
            ]),
            'https://mybusinessbusinessinformation.googleapis.com/v1/accounts/123/locations*' => Http::response([
                'locations' => [['name' => 'locations/456', 'title' => 'Managed Court']],
            ]),
        ]);
        $client = new GoogleBusinessProfileHttpClient;

        $authorization = $client->authorizationUrl(str_repeat('s', 64));
        $tokens = $client->exchangeCode('server-code');
        $accounts = $client->accounts($tokens->accessToken);
        $locations = $client->locations($tokens->accessToken, $accounts[0]['name']);

        $this->assertStringContainsString(rawurlencode('https://www.googleapis.com/auth/business.manage'), $authorization);
        $this->assertStringContainsString('access_type=offline', $authorization);
        $this->assertSame('http-refresh-token', $tokens->refreshToken);
        $this->assertSame('locations/456', $locations[0]['name']);
        Http::assertSent(fn ($request): bool => str_starts_with($request->url(), 'https://mybusinessaccountmanagement.googleapis.com/v1/accounts')
            && $request->hasHeader('Authorization', 'Bearer http-access-token'));
        Http::assertSent(fn ($request): bool => str_starts_with($request->url(), 'https://mybusinessbusinessinformation.googleapis.com/v1/accounts/123/locations')
            && str_contains($request->url(), 'readMask=')
            && $request->hasHeader('Authorization', 'Bearer http-access-token'));
    }

    public function test_http_adapter_translates_google_rate_limits_without_exposing_response_details(): void
    {
        $this->configureHttpClient();
        Http::fake([
            'https://mybusinessaccountmanagement.googleapis.com/v1/accounts*' => Http::response([
                'error' => ['status' => 'RESOURCE_EXHAUSTED', 'message' => 'upstream internal detail'],
            ], 429),
        ]);

        try {
            (new GoogleBusinessProfileHttpClient)->accounts('access-token');
            $this->fail('The adapter should reject a rate-limited response.');
        } catch (GoogleBusinessProfileException $exception) {
            $this->assertSame('RESOURCE_EXHAUSTED', $exception->errorCode);
            $this->assertSame('Google is receiving too many requests right now. Please wait a moment and try again.', $exception->getMessage());
            $this->assertStringNotContainsString('upstream internal detail', $exception->getMessage());
        }
    }

    private function configureHttpClient(): void
    {
        config([
            'google.business_profile.enabled' => true,
            'google.business_profile.client_id' => 'client-id',
            'google.business_profile.client_secret' => 'client-secret',
            'google.business_profile.redirect_uri' => 'https://finacourt.example/owner/google-business-profile/callback',
        ]);
    }

    private function fakeGoogle(array $locations = []): FakeGoogleBusinessProfileClient
    {
        $fake = new FakeGoogleBusinessProfileClient($locations);
        $this->app->instance(GoogleBusinessProfileClient::class, $fake);

        return $fake;
    }

    /** @return array{User, Venue, CourtResource} */
    private function inventory(string $slug, ?Organization $organization = null, ?User $owner = null): array
    {
        $organization ??= Organization::factory()->create();
        $owner ??= User::factory()->create();

        if (! Membership::query()->where('organization_id', $organization->getKey())->where('user_id', $owner->getKey())->exists()) {
            Membership::factory()->owner()->for($owner)->for($organization)->create();
        }

        $venue = Venue::factory()->for($organization)->published()->create([
            'name' => 'FinACourt '.str_replace('-', ' ', $slug),
            'slug' => $slug,
            'address' => '10 Court Street',
            'city' => 'Marawi City',
            'city_slug' => 'marawi-city',
            'province' => 'Lanao del Sur',
            'province_slug' => 'lanao-del-sur',
            'latitude' => 8.0,
            'longitude' => 124.29,
            'phone' => '+63 917 555 0100',
        ]);
        $sport = Sport::factory()->create(['name' => 'Pickleball '.$slug, 'slug' => 'pickleball-'.$slug, 'is_active' => true]);
        $venue->sports()->attach($sport);
        $resource = CourtResource::factory()->for($venue)->for($sport)->create(['is_active' => true]);

        foreach (range(0, 6) as $day) {
            OperatingHour::factory()->for($venue)->create([
                'day_of_week' => $day,
                'is_closed' => false,
                'opens_at' => '08:00',
                'closes_at' => '22:00',
            ]);
        }

        return [$owner, $venue, $resource];
    }

    /** @return array<string, mixed> */
    private function location(Venue $venue, string $name, string $placeId = ''): array
    {
        return [
            'name' => $name,
            'title' => $venue->name,
            'storefrontAddress' => [
                'addressLines' => [$venue->address],
                'locality' => $venue->city,
                'administrativeArea' => $venue->province,
                'regionCode' => 'PH',
            ],
            'phoneNumbers' => ['primaryPhone' => $venue->phone],
            'latlng' => ['latitude' => (float) $venue->latitude, 'longitude' => (float) $venue->longitude],
            'metadata' => ['placeId' => $placeId],
            'categories' => ['primaryCategory' => ['displayName' => 'Sports complex']],
        ];
    }
}

class FakeGoogleBusinessProfileClient implements GoogleBusinessProfileClient
{
    public ?string $lastState = null;

    /** @var array<int, string> */
    public array $revokedTokens = [];

    public int $accountsCalls = 0;

    /** @param array<int, array<string, mixed>> $locations */
    public function __construct(private readonly array $profileLocations = []) {}

    public function available(): bool
    {
        return true;
    }

    public function authorizationUrl(string $state): string
    {
        $this->lastState = $state;

        return 'https://accounts.google.test/oauth?'.http_build_query(['state' => $state]);
    }

    public function exchangeCode(string $code): GoogleOAuthTokens
    {
        return new GoogleOAuthTokens(
            'access-secret',
            'refresh-secret',
            CarbonImmutable::now('UTC')->addHour(),
            ['https://www.googleapis.com/auth/business.manage'],
        );
    }

    public function refresh(string $refreshToken): GoogleOAuthTokens
    {
        return $this->exchangeCode('refresh');
    }

    public function accounts(string $accessToken): array
    {
        $this->accountsCalls++;

        return [[
            'name' => 'accounts/123',
            'accountName' => 'Venue owner account',
            'role' => 'OWNER',
            'verificationState' => 'VERIFIED',
        ]];
    }

    public function locations(string $accessToken, string $accountName): array
    {
        return $this->profileLocations;
    }

    public function revoke(string $token): void
    {
        $this->revokedTokens[] = $token;
    }
}

class FailingGoogleBusinessProfileClient extends FakeGoogleBusinessProfileClient
{
    public function accounts(string $accessToken): array
    {
        throw new GoogleBusinessProfileException(
            'PERMISSION_DENIED',
            'Google did not allow this request. FinACourt may still need Business Profile API approval.',
        );
    }
}

class RateLimitedGoogleBusinessProfileClient extends FakeGoogleBusinessProfileClient
{
    public function accounts(string $accessToken): array
    {
        $this->accountsCalls++;

        throw new GoogleBusinessProfileException(
            'RESOURCE_EXHAUSTED',
            'Google is receiving too many requests right now. Please wait a moment and try again.',
        );
    }
}
