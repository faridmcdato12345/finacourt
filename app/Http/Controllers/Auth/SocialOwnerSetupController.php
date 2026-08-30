<?php

namespace App\Http\Controllers\Auth;

use App\Enums\MembershipRole;
use App\Http\Controllers\Controller;
use App\Models\Membership;
use App\Models\Organization;
use App\SalesPartners\PartnerRegistrationAttributor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class SocialOwnerSetupController extends Controller
{
    public function create(Request $request): Response|RedirectResponse
    {
        if ($request->user()->memberships()->exists()) {
            return redirect()->route('owner.dashboard');
        }

        abort_unless((bool) $request->session()->get('social_auth.owner_setup_required', false), 403);

        return Inertia::render('Auth/CompleteOwnerSetup', [
            'user' => $request->user()->only(['name', 'email']),
        ]);
    }

    public function store(Request $request, PartnerRegistrationAttributor $partnerAttribution): RedirectResponse
    {
        abort_unless((bool) $request->session()->get('social_auth.owner_setup_required', false), 403);

        $validated = $request->validate([
            'organization_name' => ['required', 'string', 'max:255'],
        ]);

        $organization = DB::transaction(function () use ($request, $validated, $partnerAttribution): Organization {
            $user = $request->user()->newQuery()->lockForUpdate()->findOrFail($request->user()->getKey());
            $existing = $user->memberships()->oldest('id')->first();

            if ($existing !== null) {
                return $existing->organization;
            }

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

            return $organization;
        });

        $request->session()->forget('social_auth.owner_setup_required');
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
