<?php

namespace Tests\Feature;

use App\Enums\MembershipRole;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\QueuedVerifyEmail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_page_exposes_the_inertia_initial_page_payload(): void
    {
        $this->get('/register')
            ->assertOk()
            ->assertSee('<script data-page="app" type="application/json">', false)
            ->assertSee('"component":"Auth\\/Register"', false);
    }

    public function test_public_header_gives_players_and_court_owners_clear_login_paths(): void
    {
        $this->get(route('marketplace.home'))
            ->assertOk()
            ->assertSee('Player log in')
            ->assertSee('Owner log in')
            ->assertSee('href="'.route('player.login').'"', false)
            ->assertSee('href="'.route('login').'"', false)
            ->assertSee('href="'.route('marketplace.for-owners').'"', false)
            ->assertSee('data-owner-registration-link href="'.route('register').'"', false)
            ->assertDontSee('data-owner-registration-link href="'.route('marketplace.for-owners').'"', false)
            ->assertSee('List your courts');
    }

    public function test_owner_can_register_with_a_new_organization(): void
    {
        Notification::fake();

        $response = $this->post('/register', [
            'name' => 'Alicia Owner',
            'email' => 'alicia@example.com',
            'organization_name' => 'Alicia Sports Center',
            'password' => 'secure-password',
            'password_confirmation' => 'secure-password',
        ]);

        $user = User::query()->where('email', 'alicia@example.com')->firstOrFail();
        $organization = Organization::query()->where('name', 'Alicia Sports Center')->firstOrFail();
        $membership = Membership::query()->firstOrFail();

        $response->assertRedirect(route('verification.notice'));
        $this->assertAuthenticatedAs($user);
        $this->assertSame($organization->getKey(), $membership->organization_id);
        $this->assertSame($user->getKey(), $membership->user_id);
        $this->assertSame(MembershipRole::Owner, $membership->role);
        $this->assertSame($organization->getKey(), session('tenant.organization_id'));
        Notification::assertSentTo(
            $user,
            function (QueuedVerifyEmail $notification) use ($user): bool {
                return $notification instanceof ShouldQueue
                    && $notification->queue === 'emails'
                    && in_array('mail', $notification->via($user), true);
            },
        );
    }

    public function test_unverified_owner_can_manage_their_account_but_cannot_enter_or_change_the_workspace(): void
    {
        Notification::fake();
        $owner = User::factory()->unverified()->create([
            'email' => 'unverified-owner@example.com',
            'password' => 'secure-password',
        ]);
        $organization = Organization::factory()->create();
        Membership::factory()->owner()->for($owner)->for($organization)->create();

        $this->actingAs($owner)
            ->withSession(['tenant.organization_id' => $organization->getKey()])
            ->get(route('verification.notice'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/VerifyEmail')
                ->where('email', 'unverified-owner@example.com')
                ->where('accountSettingsUrl', route('owner.account.edit', [], false))
                ->where('isOwnerVerification', true)
                ->where('routes.resend', route('verification.send', [], false))
                ->where('routes.logout', route('logout', [], false)));

        $this->get(route('owner.account.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Owner/Account/Edit')
                ->where('auth.user.email_verified', false)
                ->where('account.email_verified', false));

        $this->get(route('owner.dashboard'))
            ->assertRedirect(route('verification.notice'));

        $this->post(route('owner.venues.store'), [])
            ->assertRedirect(route('verification.notice'));

        $this->assertDatabaseCount('venues', 0);

        $this->patch(route('owner.account.profile.update'), [
            'name' => $owner->name,
            'email' => 'corrected-owner@example.com',
            'profile_current_password' => 'secure-password',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $owner->refresh();
        $this->assertSame('corrected-owner@example.com', $owner->email);
        $this->assertFalse($owner->hasVerifiedEmail());
        Notification::assertSentTo($owner, QueuedVerifyEmail::class);
    }

    public function test_email_verification_notice_supports_inertia_navigation_without_an_html_modal(): void
    {
        $owner = User::factory()->unverified()->create([
            'email' => 'inertia-owner@example.com',
        ]);
        $organization = Organization::factory()->create();
        Membership::factory()->owner()->for($owner)->for($organization)->create();
        $inertiaVersion = app(HandleInertiaRequests::class)->version(
            Request::create(route('verification.notice')),
        );

        $this->actingAs($owner)
            ->withSession(['tenant.organization_id' => $organization->getKey()])
            ->withHeaders([
                'X-Inertia' => 'true',
                'X-Inertia-Version' => $inertiaVersion,
            ])
            ->get(route('verification.notice'))
            ->assertOk()
            ->assertHeader('X-Inertia', 'true')
            ->assertJsonPath('component', 'Auth/VerifyEmail')
            ->assertJsonPath('props.email', 'inertia-owner@example.com')
            ->assertJsonPath('props.isOwnerVerification', true);
    }

    public function test_unverified_owner_login_returns_to_email_verification(): void
    {
        $owner = User::factory()->unverified()->create([
            'email' => 'pending-owner@example.com',
            'password' => 'secure-password',
        ]);
        $organization = Organization::factory()->create();
        Membership::factory()->owner()->for($owner)->for($organization)->create();

        $this->post(route('login'), [
            'email' => $owner->email,
            'password' => 'secure-password',
        ])->assertRedirect(route('verification.notice'));

        $this->assertAuthenticatedAs($owner);
    }

    public function test_owner_can_authenticate_and_reach_the_dashboard(): void
    {
        $owner = User::factory()->create(['password' => 'secure-password']);
        $organization = Organization::factory()->create();
        Membership::factory()->owner()->for($owner)->for($organization)->create();

        $response = $this->post('/login', [
            'email' => $owner->email,
            'password' => 'secure-password',
        ]);

        $response->assertRedirect(route('owner.dashboard'));
        $this->assertAuthenticatedAs($owner);
        $this->get(route('owner.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Owner/Dashboard')
                ->has('inventory')
                ->has('today')
                ->has('marketplace'));
    }

    public function test_invalid_credentials_do_not_authenticate_a_user(): void
    {
        $user = User::factory()->create();

        $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'incorrect-password',
        ])->assertRedirect('/login')->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_unauthenticated_users_cannot_access_protected_dashboards(): void
    {
        $this->get(route('owner.dashboard'))->assertRedirect(route('login'));
        $this->get(route('platform.dashboard'))->assertRedirect(route('login'));
    }
}
