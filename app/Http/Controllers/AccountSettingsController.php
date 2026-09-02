<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateAccountPasswordRequest;
use App\Http\Requests\UpdateAccountProfileRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response;

class AccountSettingsController extends Controller
{
    public function ownerEdit(Request $request): Response
    {
        return Inertia::render('Owner/Account/Edit', [
            'account' => $this->accountData($request->user()),
            'routes' => [
                'profile' => route('owner.account.profile.update', [], false),
                'password' => route('owner.account.password.update', [], false),
                'password_link' => route('owner.account.password-link.store', [], false),
            ],
        ]);
    }

    public function playerEdit(Request $request): View
    {
        return view('player.account.edit', [
            'account' => $this->accountData($request->user()),
            'seo' => [
                'title' => 'Your player account',
                'description' => 'Update your FinACourt name, email address, and password.',
                'canonical' => route('player.account.edit'),
                'robots' => 'noindex,nofollow',
                'type' => 'website',
            ],
            'structuredData' => [],
        ]);
    }

    public function updateProfile(UpdateAccountProfileRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $validated = $request->safe()->only(['name', 'email']);
        $emailChanged = $validated['email'] !== $user->email;

        $user->forceFill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'email_verified_at' => $emailChanged ? null : $user->email_verified_at,
        ])->save();

        if ($emailChanged) {
            $user->sendEmailVerificationNotification();

            return back()->with('status', 'Your profile was saved. Check your new email address for a verification link.');
        }

        return back()->with('status', 'Your profile details were saved.');
    }

    public function updatePassword(UpdateAccountPasswordRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->forceFill([
            'password' => $request->validated('password'),
            'remember_token' => Str::random(60),
        ])->save();

        $request->session()->regenerate();

        return back()->with('status', 'Your password was changed.');
    }

    /** @return array<string, mixed> */
    private function accountData(User $user): array
    {
        return [
            'name' => $user->name,
            'email' => $user->email,
            'email_verified' => $user->hasVerifiedEmail(),
            'connected_sign_ins' => $user->socialAccounts()
                ->orderBy('provider')
                ->pluck('provider')
                ->unique()
                ->map(fn (string $provider): string => Str::headline($provider))
                ->values()
                ->all(),
        ];
    }
}
