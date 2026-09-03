<?php

namespace Tests\Feature;

use App\Enums\MembershipRole;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        $response->assertRedirect(route('owner.dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertSame($organization->getKey(), $membership->organization_id);
        $this->assertSame($user->getKey(), $membership->user_id);
        $this->assertSame(MembershipRole::Owner, $membership->role);
        $this->assertSame($organization->getKey(), session('tenant.organization_id'));
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
