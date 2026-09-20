<?php

namespace App\Http\Controllers;

use App\Enums\MembershipRole;
use App\Enums\OrganizationPermission;
use App\Models\Membership;
use App\Models\StaffInvitation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class StaffInvitationAcceptanceController extends Controller
{
    public function show(Request $request, string $token): Response
    {
        $invitation = $this->find($token);
        $invitation->loadMissing('organization:id,name,timezone');
        $existingUser = User::query()->where('email', $invitation->email)->first();
        $signedInUser = $request->user();
        $emailMatches = $signedInUser !== null && $signedInUser->is($existingUser);
        $requiresLogin = $invitation->isUsable() && $existingUser !== null && $signedInUser === null;
        $accountMismatch = $invitation->isUsable() && $signedInUser !== null && ! $emailMatches;

        if ($requiresLogin) {
            $request->session()->put('url.intended', route('staff-invitations.show', ['token' => $token]));
        }

        return Inertia::render('StaffInvitations/Show', [
            'invitation' => [
                'organization' => $invitation->organization->name,
                'email' => $invitation->email,
                'status' => $invitation->status(),
                'expires_at' => $invitation->expires_at
                    ->setTimezone($invitation->organization->timezone)
                    ->format('M j, Y g:i A T'),
                'permissions' => collect($invitation->permissions)
                    ->map(fn (string $permission): ?string => OrganizationPermission::tryFrom($permission)?->label())
                    ->filter()
                    ->values(),
            ],
            'requiresLogin' => $requiresLogin,
            'requiresRegistration' => $invitation->isUsable() && $existingUser === null && $signedInUser === null,
            'accountMismatch' => $accountMismatch,
            'canAccept' => $invitation->isUsable() && $emailMatches,
            'acceptUrl' => route('staff-invitations.accept', ['token' => $token], false),
            'loginUrl' => route('login', [], false),
            'logoutUrl' => route('logout', [], false),
        ]);
    }

    public function accept(Request $request, string $token): RedirectResponse
    {
        $invitation = $this->find($token);
        $existingUser = User::query()->where('email', $invitation->email)->first();
        $registration = [];

        if ($existingUser === null && $request->user() === null) {
            $registration = $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'password' => ['required', 'confirmed', Password::defaults()],
            ]);
        }

        [$user, $created] = DB::transaction(function () use ($request, $token, $registration): array {
            $invitation = StaffInvitation::query()
                ->where('token_hash', StaffInvitation::hashToken($token))
                ->lockForUpdate()
                ->firstOrFail();

            if (! $invitation->isUsable()) {
                throw ValidationException::withMessages([
                    'invitation' => 'This staff invitation is no longer available. Ask the court owner to send a new invitation.',
                ]);
            }

            $user = User::query()->where('email', $invitation->email)->lockForUpdate()->first();
            $created = false;

            if ($user !== null) {
                if ($request->user() === null || ! $request->user()->is($user)) {
                    throw ValidationException::withMessages([
                        'invitation' => 'Sign in with the invited email address before accepting this invitation.',
                    ]);
                }
            } else {
                if ($request->user() !== null) {
                    throw ValidationException::withMessages([
                        'invitation' => 'Sign out before creating the invited staff account.',
                    ]);
                }

                $user = User::query()->create([
                    'name' => trim($registration['name']),
                    'email' => $invitation->email,
                    'email_verified_at' => now('UTC'),
                    'password' => $registration['password'],
                ]);
                $created = true;
            }

            if ($user->is_platform_admin) {
                throw ValidationException::withMessages([
                    'invitation' => 'Platform administrator accounts cannot join venue teams.',
                ]);
            }

            $membership = Membership::query()
                ->where('organization_id', $invitation->organization_id)
                ->where('user_id', $user->getKey())
                ->lockForUpdate()
                ->first();

            if ($membership?->role === MembershipRole::Owner) {
                throw ValidationException::withMessages([
                    'invitation' => 'This account is already an owner of the organization.',
                ]);
            }

            $attributes = [
                'role' => MembershipRole::Staff,
                'permissions' => $invitation->permissions,
                'joined_at' => now('UTC'),
                'suspended_at' => null,
                'suspended_by_user_id' => null,
                'removed_at' => null,
                'removed_by_user_id' => null,
            ];

            if ($membership === null) {
                $membership = Membership::query()->create([
                    'organization_id' => $invitation->organization_id,
                    'user_id' => $user->getKey(),
                    ...$attributes,
                ]);
            } else {
                $membership->update($attributes);
            }

            if (! $user->hasVerifiedEmail()) {
                $user->markEmailAsVerified();
            }

            $invitation->update([
                'accepted_at' => now('UTC'),
                'accepted_by_user_id' => $user->getKey(),
            ]);

            return [$user, $created];
        }, 5);

        if ($created) {
            Auth::login($user);
        }

        $request->session()->regenerate();
        $request->session()->put('tenant.organization_id', $invitation->organization_id);

        return redirect()->route('owner.dashboard')
            ->with('status', 'Invitation accepted. Welcome to the team.');
    }

    private function find(string $token): StaffInvitation
    {
        return StaffInvitation::query()
            ->where('token_hash', StaffInvitation::hashToken($token))
            ->firstOrFail();
    }
}
