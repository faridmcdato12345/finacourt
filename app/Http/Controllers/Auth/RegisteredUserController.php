<?php

namespace App\Http\Controllers\Auth;

use App\Enums\MembershipRole;
use App\Http\Controllers\Controller;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use App\SalesPartners\PartnerRegistrationAttributor;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    public function store(Request $request, PartnerRegistrationAttributor $partnerAttribution): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users'],
            'organization_name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        [$user, $organization] = DB::transaction(function () use ($validated, $request, $partnerAttribution) {
            $user = User::query()->create([
                'name' => $validated['name'],
                'email' => Str::lower($validated['email']),
                'password' => $validated['password'],
            ]);

            $organization = Organization::query()->create([
                'name' => $validated['organization_name'],
                'slug' => $this->uniqueSlug($validated['organization_name']),
            ]);

            Membership::query()->create([
                'organization_id' => $organization->getKey(),
                'user_id' => $user->getKey(),
                'role' => MembershipRole::Owner,
                'joined_at' => now(),
            ]);

            $partnerAttribution->attribute($request, $user, $organization);

            return [$user, $organization];
        });

        event(new Registered($user));
        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('tenant.organization_id', $organization->getKey());

        return redirect()->route('owner.dashboard');
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'organization';

        do {
            $slug = $base.'-'.Str::lower(Str::random(6));
        } while (Organization::query()->where('slug', $slug)->exists());

        return $slug;
    }
}
