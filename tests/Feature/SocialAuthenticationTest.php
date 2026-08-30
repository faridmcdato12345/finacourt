<?php

namespace Tests\Feature;

use App\Enums\MembershipRole;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

class SocialAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'social_auth.providers.google.enabled' => true,
            'services.google.client_id' => 'google-client',
            'services.google.client_secret' => 'google-secret',
            'services.google.redirect' => url('/auth/google/callback'),
        ]);
    }

    public function test_configured_social_provider_is_offered_to_owners_and_players(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/Login')
                ->where('socialProviders.0.key', 'google')
                ->where('socialProviders.0.label', 'Google'));

        $this->get(route('player.register'))
            ->assertOk()
            ->assertSee('Continue with Google');
    }

    public function test_unconfigured_provider_is_not_shown_and_cannot_be_started(): void
    {
        config(['social_auth.providers.google.enabled' => false]);

        $this->get(route('login'))
            ->assertInertia(fn (Assert $page) => $page->where('socialProviders', []));

        $this->get(route('social.redirect', ['audience' => 'owner', 'provider' => 'google']))
            ->assertNotFound();
    }

    public function test_anonymous_player_can_register_with_a_verified_google_identity(): void
    {
        Socialite::fake('google', $this->socialUser('google-player', 'player@example.com', 'Pat Player'));

        $this->get(route('social.redirect', ['audience' => 'player', 'provider' => 'google']))
            ->assertRedirect('https://socialite.fake/google/authorize');

        $response = $this->get(route('social.callback', ['provider' => 'google']));
        $user = User::query()->where('email', 'player@example.com')->firstOrFail();

        $response->assertRedirect(route('player.bookings.index'));
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->email_verified_at);
        $this->assertDatabaseHas('social_accounts', [
            'user_id' => $user->getKey(),
            'provider' => 'google',
            'provider_user_id' => 'google-player',
            'provider_email' => 'player@example.com',
        ]);
    }

    public function test_player_return_path_survives_social_sign_in(): void
    {
        Socialite::fake('google', $this->socialUser('return-player', 'return@example.com'));
        $return = '/venues/sample/reserve?resource=8';

        $this->get(route('social.redirect', [
            'audience' => 'player',
            'provider' => 'google',
            'return' => $return,
        ]));

        $this->get(route('social.callback', ['provider' => 'google']))
            ->assertRedirect(url($return));
    }

    public function test_existing_user_is_linked_only_when_provider_confirms_email(): void
    {
        $user = User::factory()->create([
            'email' => 'existing@example.com',
            'password' => 'keep-this-password',
            'email_verified_at' => null,
        ]);
        Socialite::fake('google', $this->socialUser('existing-google', 'existing@example.com'));

        $this->withSession(['social_auth.audience' => 'player'])
            ->get(route('social.callback', ['provider' => 'google']))
            ->assertRedirect(route('player.bookings.index'));

        $this->assertSame(1, User::query()->where('email', 'existing@example.com')->count());
        $this->assertTrue(Hash::check('keep-this-password', $user->fresh()->password));
        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->assertDatabaseHas('social_accounts', ['user_id' => $user->getKey(), 'provider' => 'google']);
    }

    public function test_unverified_provider_email_cannot_take_over_an_existing_account(): void
    {
        $user = User::factory()->create(['email' => 'protected@example.com']);
        $socialUser = $this->socialUser('unverified-google', 'protected@example.com', verified: false);
        Socialite::fake('google', $socialUser);

        $this->withSession(['social_auth.audience' => 'player'])
            ->get(route('social.callback', ['provider' => 'google']))
            ->assertRedirect(route('player.login'))
            ->assertSessionHasErrors('social');

        $this->assertGuest();
        $this->assertDatabaseMissing('social_accounts', ['user_id' => $user->getKey()]);
    }

    public function test_repeat_social_sign_in_reuses_the_linked_user(): void
    {
        $user = User::factory()->create(['email' => 'linked@example.com']);
        SocialAccount::query()->create([
            'user_id' => $user->getKey(),
            'provider' => 'google',
            'provider_user_id' => 'linked-google',
            'provider_email' => 'linked@example.com',
        ]);
        Socialite::fake('google', $this->socialUser('linked-google', 'changed@example.com'));

        $this->withSession(['social_auth.audience' => 'player'])
            ->get(route('social.callback', ['provider' => 'google']))
            ->assertRedirect(route('player.bookings.index'));

        $this->assertAuthenticatedAs($user);
        $this->assertSame(1, SocialAccount::query()->count());
        $this->assertDatabaseMissing('users', ['email' => 'changed@example.com']);
    }

    public function test_social_owner_finishes_business_setup_before_entering_owner_pages(): void
    {
        Socialite::fake('google', $this->socialUser('google-owner', 'owner-social@example.com', 'Olivia Owner'));

        $this->withSession(['social_auth.audience' => 'owner'])
            ->get(route('social.callback', ['provider' => 'google']))
            ->assertRedirect(route('owner.social-setup.create'));

        $this->get(route('owner.social-setup.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/CompleteOwnerSetup')
                ->where('user.email', 'owner-social@example.com'));

        $response = $this->post(route('owner.social-setup.store'), [
            'organization_name' => 'Olivia Pickleball',
        ]);
        $user = User::query()->where('email', 'owner-social@example.com')->firstOrFail();
        $organization = Organization::query()->where('name', 'Olivia Pickleball')->firstOrFail();
        $membership = Membership::query()->whereBelongsTo($user)->firstOrFail();

        $response->assertRedirect(route('owner.dashboard'));
        $this->assertSame(MembershipRole::Owner, $membership->role);
        $this->assertSame($organization->getKey(), $membership->organization_id);
        $this->assertSame($organization->getKey(), session('tenant.organization_id'));
    }

    public function test_regular_authenticated_user_cannot_open_social_owner_setup_without_the_flow_flag(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('owner.social-setup.create'))
            ->assertForbidden();
    }

    public function test_player_social_login_does_not_grant_owner_tenant_access(): void
    {
        Socialite::fake('google', $this->socialUser('player-only', 'player-only@example.com'));

        $this->withSession(['social_auth.audience' => 'player'])
            ->get(route('social.callback', ['provider' => 'google']));

        $this->get(route('owner.dashboard'))->assertForbidden();
        $this->assertDatabaseCount('memberships', 0);
    }

    public function test_apple_form_post_callback_can_authenticate_without_csrf_bypass_elsewhere(): void
    {
        $this->enableApple();
        Socialite::fake('apple', $this->socialUser('apple-player', 'apple@example.com'));

        $this->withSession(['social_auth.audience' => 'player'])
            ->post(route('social.callback', ['provider' => 'apple']))
            ->assertRedirect(route('player.bookings.index'));

        $this->assertDatabaseHas('social_accounts', ['provider' => 'apple', 'provider_user_id' => 'apple-player']);
    }

    public function test_apple_start_uses_registered_provider_and_short_lived_cross_site_context_cookie(): void
    {
        $this->enableApple();

        $response = $this->get(route('social.redirect', ['audience' => 'player', 'provider' => 'apple']));
        $cookie = collect($response->headers->getCookies())
            ->first(fn ($cookie) => $cookie->getName() === 'finacourt_apple_signin');

        $response->assertRedirectContains('https://appleid.apple.com/auth/authorize');
        $this->assertNotNull($cookie);
        $this->assertTrue($cookie->isSecure());
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('none', $cookie->getSameSite());
        $this->assertNotSame('', session('state'));
    }

    public function test_apple_context_recovers_player_destination_when_lax_session_cookie_is_absent(): void
    {
        $this->enableApple();
        Socialite::fake('apple', $this->socialUser('apple-recovered', 'recovered@example.com'));

        $context = json_encode([
            'audience' => 'player',
            'state' => 'trusted-oauth-state',
            'intended' => '/player/bookings',
            'issued_at' => now()->timestamp,
        ], JSON_THROW_ON_ERROR);

        $this->withCookie('finacourt_apple_signin', $context)
            ->post(route('social.callback', ['provider' => 'apple']))
            ->assertRedirect(url('/player/bookings'))
            ->assertCookieExpired('finacourt_apple_signin');

        $this->assertDatabaseHas('social_accounts', ['provider_user_id' => 'apple-recovered']);
    }

    private function socialUser(
        string $id,
        string $email,
        string $name = 'Social Player',
        bool $verified = true,
    ): SocialiteUser {
        return SocialiteUser::fake([
            'id' => $id,
            'email' => $email,
            'name' => $name,
            'email_verified' => $verified,
            'verified_email' => $verified,
            'verified' => $verified,
        ]);
    }

    private function enableApple(): void
    {
        config([
            'social_auth.providers.apple.enabled' => true,
            'services.apple.client_id' => 'com.finacourt.web',
            'services.apple.client_secret' => 'signed-client-secret',
            'services.apple.redirect' => url('/auth/apple/callback'),
        ]);
    }
}
