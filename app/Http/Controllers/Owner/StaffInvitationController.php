<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreStaffInvitationRequest;
use App\Models\Membership;
use App\Models\StaffInvitation;
use App\Models\User;
use App\Organizations\StaffInvitationManager;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class StaffInvitationController extends Controller
{
    public function store(
        StoreStaffInvitationRequest $request,
        TenantContext $context,
        StaffInvitationManager $invitations,
    ): RedirectResponse {
        $organization = $context->organization();
        $validated = $request->validated();
        $existingUser = User::query()->where('email', $validated['email'])->first();

        if ($existingUser?->is_platform_admin) {
            throw ValidationException::withMessages([
                'email' => 'Platform administrator accounts cannot be added as venue staff.',
            ]);
        }

        if ($existingUser && Membership::query()
            ->where('organization_id', $organization->getKey())
            ->where('user_id', $existingUser->getKey())
            ->whereNull('removed_at')
            ->exists()) {
            throw ValidationException::withMessages([
                'email' => 'This person already belongs to your team.',
            ]);
        }

        $invitations->invite(
            $organization,
            $request->user(),
            $validated['email'],
            $validated['permissions'],
        );

        return back()->with('status', "Staff invitation sent to {$validated['email']}.");
    }

    public function resend(
        Request $request,
        int $invitation,
        TenantContext $context,
        StaffInvitationManager $invitations,
    ): RedirectResponse {
        $pending = $this->invitation($invitation, $context);
        $invitations->resend($pending, $request->user());

        return back()->with('status', "A fresh invitation was sent to {$pending->email}.");
    }

    public function revoke(
        Request $request,
        int $invitation,
        TenantContext $context,
        StaffInvitationManager $invitations,
    ): RedirectResponse {
        $pending = $this->invitation($invitation, $context);
        $invitations->revoke($pending, $request->user());

        return back()->with('status', "Invitation for {$pending->email} revoked.");
    }

    private function invitation(int $invitation, TenantContext $context): StaffInvitation
    {
        $organization = $context->organization();
        Gate::authorize('manageMembers', $organization);

        return StaffInvitation::query()
            ->whereKey($invitation)
            ->where('organization_id', $organization->getKey())
            ->whereNull('accepted_at')
            ->firstOrFail();
    }
}
