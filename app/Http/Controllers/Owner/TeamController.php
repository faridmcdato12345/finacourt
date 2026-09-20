<?php

namespace App\Http\Controllers\Owner;

use App\Enums\MembershipRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateStaffMembershipRequest;
use App\Models\Membership;
use App\Organizations\StaffPermissionSet;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TeamController extends Controller
{
    public function index(TenantContext $context, StaffPermissionSet $permissionSet): Response
    {
        $organization = $context->organization();
        Gate::authorize('manageMembers', $organization);

        $staff = $organization->memberships()
            ->where('role', MembershipRole::Staff)
            ->whereNull('removed_at')
            ->with(['user:id,name,email,email_verified_at', 'suspendedBy:id,name'])
            ->orderByDesc('joined_at')
            ->get()
            ->map(fn (Membership $membership): array => [
                'id' => $membership->getKey(),
                'name' => $membership->user->name,
                'email' => $membership->user->email,
                'email_verified' => $membership->user->hasVerifiedEmail(),
                'permissions' => array_values(array_intersect(
                    $membership->permissions ?? [],
                    $permissionSet->allowedValues(),
                )),
                'joined_at' => $membership->joined_at?->setTimezone($organization->timezone)->format('M j, Y'),
                'status' => $membership->suspended_at === null ? 'active' : 'suspended',
                'suspended_at' => $membership->suspended_at?->setTimezone($organization->timezone)->format('M j, Y g:i A'),
                'suspended_by' => $membership->suspendedBy?->name,
            ]);

        $invitations = $organization->staffInvitations()
            ->whereNull('accepted_at')
            ->latest('last_sent_at')
            ->get()
            ->map(fn ($invitation): array => [
                'id' => $invitation->getKey(),
                'email' => $invitation->email,
                'permissions' => array_values(array_intersect(
                    $invitation->permissions ?? [],
                    $permissionSet->allowedValues(),
                )),
                'status' => $invitation->status(),
                'status_label' => ucfirst($invitation->status()),
                'expires_at' => $invitation->expires_at->setTimezone($organization->timezone)->format('M j, Y g:i A'),
                'last_sent_at' => $invitation->last_sent_at->setTimezone($organization->timezone)->format('M j, Y g:i A'),
                'can_resend' => $invitation->revoked_at === null,
                'can_revoke' => $invitation->revoked_at === null,
            ]);

        return Inertia::render('Owner/Team/Index', [
            'staff' => $staff,
            'invitations' => $invitations,
            'permissionOptions' => $permissionSet->options(),
            'timezone' => $organization->timezone,
        ]);
    }

    public function update(
        UpdateStaffMembershipRequest $request,
        int $membership,
        TenantContext $context,
        StaffPermissionSet $permissionSet,
    ): RedirectResponse {
        $staff = $this->staff($membership, $context);
        $staff->update(['permissions' => $permissionSet->normalize($request->validated('permissions'))]);

        return back()->with('status', "Permissions updated for {$staff->user->name}.");
    }

    public function suspend(Request $request, int $membership, TenantContext $context): RedirectResponse
    {
        $staff = $this->staff($membership, $context);
        $staff->update([
            'suspended_at' => $staff->suspended_at ?? now('UTC'),
            'suspended_by_user_id' => $request->user()->getKey(),
        ]);

        return back()->with('status', "Access suspended for {$staff->user->name}.");
    }

    public function reactivate(int $membership, TenantContext $context): RedirectResponse
    {
        $staff = $this->staff($membership, $context);
        $staff->update([
            'suspended_at' => null,
            'suspended_by_user_id' => null,
        ]);

        return back()->with('status', "Access restored for {$staff->user->name}.");
    }

    public function destroy(Request $request, int $membership, TenantContext $context): RedirectResponse
    {
        $staff = $this->staff($membership, $context);
        $name = $staff->user->name;
        $staff->update([
            'suspended_at' => null,
            'suspended_by_user_id' => null,
            'removed_at' => now('UTC'),
            'removed_by_user_id' => $request->user()->getKey(),
        ]);

        return back()->with('status', "{$name} was removed from this team.");
    }

    private function staff(int $membership, TenantContext $context): Membership
    {
        $organization = $context->organization();
        Gate::authorize('manageMembers', $organization);

        return Membership::query()
            ->whereKey($membership)
            ->where('organization_id', $organization->getKey())
            ->where('role', MembershipRole::Staff)
            ->whereNull('removed_at')
            ->with('user:id,name,email')
            ->firstOrFail();
    }
}
