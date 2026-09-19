<?php

namespace Tests\Feature;

use App\Enums\MembershipRole;
use App\Enums\OrganizationPermission;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\StaffInvitation;
use App\Models\User;
use App\Notifications\StaffInvitationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StaffManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_invite_a_new_staff_account_with_limited_permissions(): void
    {
        Notification::fake();
        [$owner, $organization] = $this->ownerWithOrganization();

        $this->actingAs($owner)
            ->post(route('owner.team.invitations.store'), [
                'email' => '  STAFF@EXAMPLE.COM ',
                'permissions' => [OrganizationPermission::ManageBookings->value],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $invitation = StaffInvitation::query()->sole();
        $this->assertSame('staff@example.com', $invitation->email);
        $this->assertSame([
            OrganizationPermission::ViewDashboard->value,
            OrganizationPermission::ManageBookings->value,
        ], $invitation->permissions);

        $url = $this->invitationUrl('staff@example.com');
        $token = basename(parse_url($url, PHP_URL_PATH));

        $this->assertNotSame($token, $invitation->token_hash);
        $this->assertSame(hash('sha256', $token), $invitation->token_hash);

        $this->post(route('logout'));
        $this->get($url)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('StaffInvitations/Show')
                ->where('invitation.organization', $organization->name)
                ->where('invitation.email', 'staff@example.com')
                ->where('invitation.status', 'pending')
                ->where('requiresRegistration', true)
                ->where('canAccept', false));

        $this->post(route('staff-invitations.accept', $token), [
            'name' => 'Booking Staff',
            'password' => 'Secure-password-123',
            'password_confirmation' => 'Secure-password-123',
        ])->assertRedirect(route('owner.dashboard'));

        $staff = User::query()->where('email', 'staff@example.com')->sole();
        $membership = Membership::query()
            ->whereBelongsTo($organization)
            ->whereBelongsTo($staff)
            ->sole();

        $this->assertAuthenticatedAs($staff);
        $this->assertTrue($staff->hasVerifiedEmail());
        $this->assertSame(MembershipRole::Staff, $membership->role);
        $this->assertTrue($membership->isActive());
        $this->assertNotNull($invitation->refresh()->accepted_at);
        $this->assertTrue(Gate::forUser($staff)->allows('viewDashboard', $organization));
        $this->assertTrue(Gate::forUser($staff)->allows('manageBookings', $organization));
        $this->assertFalse(Gate::forUser($staff)->allows('manageInventory', $organization));
        $this->assertFalse(Gate::forUser($staff)->allows('manageMembers', $organization));
    }

    public function test_existing_user_must_sign_in_with_the_invited_email_before_accepting(): void
    {
        Notification::fake();
        [$owner, $organization] = $this->ownerWithOrganization();
        $invitedUser = User::factory()->create(['email' => 'existing@example.com']);
        $wrongUser = User::factory()->create(['email' => 'wrong@example.com']);

        $this->actingAs($owner)->post(route('owner.team.invitations.store'), [
            'email' => $invitedUser->email,
            'permissions' => [OrganizationPermission::ManageInventory->value],
        ])->assertRedirect();

        $token = basename(parse_url($this->invitationUrl($invitedUser->email), PHP_URL_PATH));
        $this->post(route('logout'));

        $this->get(route('staff-invitations.show', $token))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('requiresLogin', true)
                ->where('requiresRegistration', false));

        $this->actingAs($wrongUser)
            ->get(route('staff-invitations.show', $token))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('accountMismatch', true)
                ->where('canAccept', false));

        $this->post(route('staff-invitations.accept', $token))
            ->assertSessionHasErrors('invitation');
        $this->assertDatabaseMissing('memberships', [
            'organization_id' => $organization->getKey(),
            'user_id' => $wrongUser->getKey(),
        ]);

        $this->actingAs($invitedUser)
            ->post(route('staff-invitations.accept', $token))
            ->assertRedirect(route('owner.dashboard'));

        $this->assertDatabaseHas('memberships', [
            'organization_id' => $organization->getKey(),
            'user_id' => $invitedUser->getKey(),
            'role' => MembershipRole::Staff->value,
        ]);
        $this->assertTrue(Gate::forUser($invitedUser)->allows('manageInventory', $organization));
    }

    public function test_owner_can_update_suspend_restore_and_remove_staff_access(): void
    {
        [$owner, $organization] = $this->ownerWithOrganization();
        $staff = User::factory()->create();
        $membership = Membership::factory()
            ->for($organization)
            ->for($staff)
            ->withPermissions([OrganizationPermission::ViewDashboard])
            ->create();

        $this->actingAs($owner)
            ->patch(route('owner.team.update', $membership), [
                'permissions' => [
                    OrganizationPermission::ManageBookings->value,
                    OrganizationPermission::ManageInventory->value,
                ],
            ])
            ->assertRedirect();

        $this->assertSame([
            OrganizationPermission::ViewDashboard->value,
            OrganizationPermission::ManageBookings->value,
            OrganizationPermission::ManageInventory->value,
        ], $membership->refresh()->permissions);

        $this->actingAs($owner)
            ->patch(route('owner.team.suspend', $membership))
            ->assertRedirect();

        $this->assertNotNull($membership->refresh()->suspended_at);
        $this->assertSame($owner->getKey(), $membership->suspended_by_user_id);
        $this->assertFalse(Gate::forUser($staff)->allows('viewDashboard', $organization));
        $this->actingAs($staff)->get(route('owner.dashboard'))->assertForbidden();

        $this->actingAs($owner)
            ->patch(route('owner.team.reactivate', $membership))
            ->assertRedirect();

        $this->assertTrue($membership->refresh()->isActive());
        $this->actingAs($staff)
            ->withSession(['tenant.organization_id' => $organization->getKey()])
            ->get(route('owner.dashboard'))
            ->assertOk();

        $this->actingAs($owner)
            ->delete(route('owner.team.destroy', $membership))
            ->assertRedirect();

        $this->assertNotNull($membership->refresh()->removed_at);
        $this->assertSame($owner->getKey(), $membership->removed_by_user_id);
        $this->assertFalse(Gate::forUser($staff)->allows('viewDashboard', $organization));
        $this->assertFalse($staff->fresh()->organizations->contains($organization));
        $this->actingAs($staff)->get(route('owner.dashboard'))->assertForbidden();
    }

    public function test_resending_rotates_the_token_and_revoking_blocks_acceptance(): void
    {
        Notification::fake();
        [$owner, $organization] = $this->ownerWithOrganization();

        $this->actingAs($owner)->post(route('owner.team.invitations.store'), [
            'email' => 'rotated@example.com',
            'permissions' => [],
        ])->assertRedirect();

        $invitation = StaffInvitation::query()->sole();
        $oldUrl = $this->invitationUrl($invitation->email);
        $oldHash = $invitation->token_hash;

        Notification::fake();
        $this->post(route('owner.team.invitations.resend', $invitation))->assertRedirect();
        $newUrl = $this->invitationUrl($invitation->email);
        $newToken = basename(parse_url($newUrl, PHP_URL_PATH));

        $this->assertNotSame($oldHash, $invitation->refresh()->token_hash);
        $this->get($oldUrl)->assertNotFound();
        $this->get($newUrl)->assertOk();

        $this->delete(route('owner.team.invitations.revoke', $invitation))->assertRedirect();
        $this->get($newUrl)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('invitation.status', 'revoked'));

        $this->post(route('logout'));
        $this->post(route('staff-invitations.accept', $newToken), [
            'name' => 'Revoked Staff',
            'password' => 'Secure-password-123',
            'password_confirmation' => 'Secure-password-123',
        ])->assertSessionHasErrors('invitation');

        $this->assertDatabaseMissing('memberships', [
            'organization_id' => $organization->getKey(),
            'role' => MembershipRole::Staff->value,
        ]);
    }

    public function test_staff_cannot_manage_the_team_or_open_financial_tools(): void
    {
        [$owner, $organization] = $this->ownerWithOrganization();
        $staff = User::factory()->create();
        Membership::factory()
            ->for($organization)
            ->for($staff)
            ->withPermissions([
                OrganizationPermission::ViewDashboard,
                OrganizationPermission::ManageStaff,
                OrganizationPermission::ManageBookings,
                OrganizationPermission::ManageInventory,
            ])
            ->create();

        $this->assertFalse(Gate::forUser($staff)->allows('manageMembers', $organization));
        $this->actingAs($staff)->get(route('owner.team.index'))->assertForbidden();
        $this->actingAs($staff)->get(route('owner.settlements.index'))->assertForbidden();

        $this->actingAs($owner)
            ->get(route('owner.team.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Owner/Team/Index')
                ->has('staff', 1)
                ->has('permissionOptions', 2));
    }

    public function test_owner_cannot_change_staff_or_invitations_from_another_organization(): void
    {
        Notification::fake();
        [$owner, $organization] = $this->ownerWithOrganization();
        $otherOrganization = Organization::factory()->create();
        $otherStaff = Membership::factory()->for($otherOrganization)->create();
        $otherInvitation = StaffInvitation::query()->create([
            'organization_id' => $otherOrganization->getKey(),
            'email' => 'other@example.com',
            'permissions' => [OrganizationPermission::ViewDashboard->value],
            'token_hash' => hash('sha256', str_repeat('a', 64)),
            'invited_by_user_id' => $owner->getKey(),
            'expires_at' => now()->addWeek(),
            'last_sent_at' => now(),
        ]);

        $this->actingAs($owner)
            ->withSession(['tenant.organization_id' => $organization->getKey()])
            ->patch(route('owner.team.suspend', $otherStaff))
            ->assertNotFound();
        $this->delete(route('owner.team.invitations.revoke', $otherInvitation))->assertNotFound();
    }

    /** @return array{User, Organization} */
    private function ownerWithOrganization(): array
    {
        $owner = User::factory()->create();
        $organization = Organization::factory()->create();
        Membership::factory()->owner()->for($owner)->for($organization)->create();

        return [$owner, $organization];
    }

    private function invitationUrl(string $email): string
    {
        $url = null;

        Notification::assertSentOnDemand(
            StaffInvitationNotification::class,
            function (
                StaffInvitationNotification $notification,
                array $channels,
                AnonymousNotifiable $notifiable,
            ) use ($email, &$url): bool {
                $url = $notification->url;

                return $channels === ['mail']
                    && $notifiable->routes['mail'] === $email;
            },
        );

        $this->assertIsString($url);

        return $url;
    }
}
