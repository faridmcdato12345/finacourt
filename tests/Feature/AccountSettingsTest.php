<?php

namespace Tests\Feature;

use App\Models\Membership;
use App\Models\Organization;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AccountSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_sent_to_the_correct_login_page(): void
    {
        $this->get(route('player.account.edit'))->assertRedirect(route('player.login'));
        $this->get(route('owner.account.edit'))->assertRedirect(route('login'));
    }

    public function test_player_can_open_account_settings_from_the_player_experience(): void
    {
        $player = User::factory()->create([
            'name' => 'Cora Player',
            'email' => 'cora@example.com',
        ]);

        SocialAccount::query()->create([
            'user_id' => $player->getKey(),
            'provider' => 'google',
            'provider_user_id' => 'google-cora',
            'provider_email' => $player->email,
        ]);

        $this->actingAs($player)
            ->get(route('player.account.edit'))
            ->assertOk()
            ->assertSee('Profile and password')
            ->assertSee('Cora Player')
            ->assertSee('cora@example.com')
            ->assertSee('Connected sign-in:')
            ->assertSee('Google')
            ->assertSee('action="'.route('player.account.profile.update', [], false).'"', false)
            ->assertSee('action="'.route('player.account.password.update', [], false).'"', false)
            ->assertSee('action="'.route('player.account.password-link.store', [], false).'"', false)
            ->assertSee('href="'.route('player.account.edit').'"', false);
    }

    public function test_owner_can_open_account_settings_inside_the_owner_workspace(): void
    {
        [$owner] = $this->ownerAccount();

        $this->actingAs($owner)
            ->get(route('owner.account.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Owner/Account/Edit')
                ->where('account.name', 'Olivia Owner')
                ->where('account.email', 'olivia@example.com')
                ->where('account.email_verified', true)
                ->where('account.connected_sign_ins', [])
                ->where('routes.profile', route('owner.account.profile.update', [], false))
                ->where('routes.password', route('owner.account.password.update', [], false))
                ->where('routes.password_link', route('owner.account.password-link.store', [], false)));
    }

    public function test_player_and_owner_can_update_their_own_name_without_entering_a_password(): void
    {
        $player = User::factory()->create(['email' => 'player@example.com']);
        [$owner] = $this->ownerAccount();

        $this->actingAs($player)
            ->patch(route('player.account.profile.update'), [
                'name' => 'Updated Player',
                'email' => 'player@example.com',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Your profile details were saved.');

        $this->assertSame('Updated Player', $player->fresh()->name);

        $this->actingAs($owner)
            ->patch(route('owner.account.profile.update'), [
                'name' => 'Updated Owner',
                'email' => 'olivia@example.com',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('Updated Owner', $owner->fresh()->name);
    }

    public function test_email_change_requires_the_current_password_and_restarts_verification(): void
    {
        Notification::fake();
        $player = User::factory()->create([
            'email' => 'old@example.com',
            'password' => 'current-password',
        ]);

        $this->actingAs($player)
            ->patch(route('player.account.profile.update'), [
                'name' => $player->name,
                'email' => 'new@example.com',
            ])
            ->assertSessionHasErrors('profile_current_password');

        $this->assertSame('old@example.com', $player->fresh()->email);

        $this->actingAs($player)
            ->patch(route('player.account.profile.update'), [
                'name' => $player->name,
                'email' => 'NEW@EXAMPLE.COM',
                'profile_current_password' => 'current-password',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Your profile was saved. Check your new email address for a verification link.');

        $player->refresh();
        $this->assertSame('new@example.com', $player->email);
        $this->assertNull($player->email_verified_at);
        Notification::assertSentTo($player, VerifyEmail::class);
    }

    public function test_user_cannot_take_another_accounts_email_address(): void
    {
        $existing = User::factory()->create(['email' => 'taken@example.com']);
        $player = User::factory()->create([
            'email' => 'player@example.com',
            'password' => 'current-password',
        ]);

        $this->actingAs($player)
            ->patch(route('player.account.profile.update'), [
                'name' => $player->name,
                'email' => strtoupper($existing->email),
                'profile_current_password' => 'current-password',
            ])
            ->assertSessionHasErrors('email');

        $this->assertSame('player@example.com', $player->fresh()->email);
    }

    public function test_player_email_verification_returns_to_the_player_area(): void
    {
        $player = User::factory()->unverified()->create();
        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(10),
            ['id' => $player->getKey(), 'hash' => sha1($player->email)],
        );

        $this->actingAs($player)
            ->get($verificationUrl)
            ->assertRedirect(route('player.bookings.index'))
            ->assertSessionHas('status', 'Your account email is verified.');

        $this->assertTrue($player->fresh()->hasVerifiedEmail());
    }

    public function test_social_sign_in_user_can_request_and_use_a_secure_password_link(): void
    {
        Notification::fake();
        $player = User::factory()->create(['password' => Str::password(48)]);
        SocialAccount::query()->create([
            'user_id' => $player->getKey(),
            'provider' => 'google',
            'provider_user_id' => 'social-password-user',
            'provider_email' => $player->email,
        ]);

        $this->actingAs($player)
            ->post(route('player.account.password-link.store'))
            ->assertRedirect()
            ->assertSessionHas('status', 'A secure password link was sent to your email address.');

        $token = null;
        Notification::assertSentTo($player, ResetPassword::class, function (ResetPassword $notification) use (&$token): bool {
            $token = $notification->token;

            return true;
        });

        $this->assertIsString($token);
        $this->get(route('password.reset', ['token' => $token, 'email' => $player->email]))
            ->assertOk()
            ->assertHeader('X-PWA-Cache', 'network-only')
            ->assertSee('Choose a new password')
            ->assertSee('action="'.route('password.store', [], false).'"', false);

        $this->post(route('password.store'), [
            'token' => $token,
            'email' => $player->email,
            'password' => 'known-new-password',
            'password_confirmation' => 'known-new-password',
        ])->assertRedirect(route('player.account.edit'))
            ->assertSessionHas('status', 'Your new password is ready to use.');

        $this->assertAuthenticatedAs($player);
        $this->assertTrue(Hash::check('known-new-password', $player->fresh()->password));
    }

    public function test_password_change_rejects_the_wrong_current_password(): void
    {
        $player = User::factory()->create(['password' => 'current-password']);
        $originalHash = $player->password;

        $this->actingAs($player)
            ->put(route('player.account.password.update'), [
                'current_password' => 'wrong-password',
                'password' => 'new-secure-password',
                'password_confirmation' => 'new-secure-password',
            ])
            ->assertSessionHasErrors('current_password');

        $this->assertSame($originalHash, $player->fresh()->password);
    }

    public function test_player_and_owner_can_change_their_password(): void
    {
        $player = User::factory()->create(['password' => 'player-current-password']);
        [$owner] = $this->ownerAccount();

        $this->actingAs($player)
            ->put(route('player.account.password.update'), [
                'current_password' => 'player-current-password',
                'password' => 'player-new-password',
                'password_confirmation' => 'player-new-password',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Your password was changed.');

        $this->assertTrue(Hash::check('player-new-password', $player->fresh()->password));

        $this->actingAs($owner)
            ->put(route('owner.account.password.update'), [
                'current_password' => 'owner-current-password',
                'password' => 'owner-new-password',
                'password_confirmation' => 'owner-new-password',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('owner-new-password', $owner->fresh()->password));
    }

    /** @return array{User, Organization} */
    private function ownerAccount(): array
    {
        $owner = User::factory()->create([
            'name' => 'Olivia Owner',
            'email' => 'olivia@example.com',
            'password' => 'owner-current-password',
        ]);
        $organization = Organization::factory()->create();
        Membership::factory()->owner()->for($owner)->for($organization)->create();

        return [$owner, $organization];
    }
}
