<?php

namespace Tests\Feature;

use App\Enums\OrganizationPermission;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TenancyAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_and_organization_membership_relationships_are_available(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $membership = Membership::factory()->owner()->for($user)->for($organization)->create();

        $this->assertTrue($user->organizations->contains($organization));
        $this->assertTrue($organization->users->contains($user));
        $this->assertTrue($user->memberships->contains($membership));
    }

    public function test_tenant_cannot_activate_another_tenants_organization(): void
    {
        [$owner, $organizationA] = $this->ownerWithOrganization();
        $organizationB = Organization::factory()->create();

        $this->actingAs($owner)
            ->withSession(['tenant.organization_id' => $organizationA->getKey()])
            ->post(route('owner.organizations.activate', $organizationB))
            ->assertForbidden();

        $this->assertSame($organizationA->getKey(), session('tenant.organization_id'));
    }

    public function test_untrusted_tenant_session_value_cannot_change_the_resolved_context(): void
    {
        [$owner, $organizationA] = $this->ownerWithOrganization();
        $organizationB = Organization::factory()->create();

        $this->actingAs($owner)
            ->withSession(['tenant.organization_id' => $organizationB->getKey()])
            ->get(route('owner.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Owner/Dashboard')
                ->where('currentOrganization.id', $organizationA->getKey())
                ->where('organization.name', $organizationA->name));

        $this->assertSame($organizationA->getKey(), session('tenant.organization_id'));
    }

    public function test_owner_cannot_authorize_changes_for_another_tenant(): void
    {
        [$owner, $organizationA] = $this->ownerWithOrganization();
        $organizationB = Organization::factory()->create();

        $this->assertTrue(Gate::forUser($owner)->allows('update', $organizationA));
        $this->assertTrue(Gate::forUser($owner)->allows('manageMembers', $organizationA));
        $this->assertFalse(Gate::forUser($owner)->allows('view', $organizationB));
        $this->assertFalse(Gate::forUser($owner)->allows('update', $organizationB));
    }

    public function test_staff_dashboard_access_requires_the_explicit_permission(): void
    {
        $organization = Organization::factory()->create();
        $authorizedStaff = User::factory()->create();
        $unauthorizedStaff = User::factory()->create();

        Membership::factory()
            ->for($authorizedStaff)
            ->for($organization)
            ->withPermissions([OrganizationPermission::ViewDashboard])
            ->create();

        Membership::factory()
            ->for($unauthorizedStaff)
            ->for($organization)
            ->withPermissions([])
            ->create();

        $this->actingAs($authorizedStaff)->get(route('owner.dashboard'))->assertOk();
        $this->actingAs($unauthorizedStaff)->get(route('owner.dashboard'))->assertForbidden();
        $this->assertFalse(Gate::forUser($authorizedStaff)->allows('update', $organization));
        $this->assertFalse(Gate::forUser($authorizedStaff)->allows('manageMembers', $organization));
    }

    public function test_staff_can_only_receive_the_specific_management_permission_granted(): void
    {
        $organization = Organization::factory()->create();
        $staff = User::factory()->create();

        Membership::factory()
            ->for($staff)
            ->for($organization)
            ->withPermissions([
                OrganizationPermission::ViewDashboard,
                OrganizationPermission::ManageOrganization,
            ])
            ->create();

        $this->assertTrue(Gate::forUser($staff)->allows('update', $organization));
        $this->assertFalse(Gate::forUser($staff)->allows('manageMembers', $organization));
    }

    public function test_platform_admin_has_explicit_platform_and_cross_tenant_access(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $organization = Organization::factory()->create();

        $this->actingAs($admin)->get(route('platform.dashboard'))->assertOk();
        $this->assertTrue(Gate::forUser($admin)->allows('view', $organization));
        $this->assertTrue(Gate::forUser($admin)->allows('update', $organization));

        $this->actingAs($admin)
            ->post(route('owner.organizations.activate', $organization))
            ->assertRedirect(route('owner.dashboard'));

        $this->get(route('owner.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('currentOrganization.id', $organization->getKey())
                ->where('currentOrganization.role', null));
    }

    public function test_regular_owner_cannot_access_platform_administration(): void
    {
        [$owner] = $this->ownerWithOrganization();

        $this->actingAs($owner)->get(route('platform.dashboard'))->assertForbidden();
    }

    /** @return array{User, Organization} */
    private function ownerWithOrganization(): array
    {
        $owner = User::factory()->create();
        $organization = Organization::factory()->create();
        Membership::factory()->owner()->for($owner)->for($organization)->create();

        return [$owner, $organization];
    }
}
