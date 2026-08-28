<?php

namespace App\Http\Controllers\Platform;

use App\Enums\SalesPartnerStatus;
use App\Http\Controllers\Controller;
use App\Models\SalesPartnerProfile;
use App\Models\User;
use App\SalesPartners\ManageSalesPartner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SalesPartnerController extends Controller
{
    public function store(Request $request, ManageSalesPartner $manager): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'exists:users,email'],
            'payout_details' => ['nullable', 'string', 'max:1000'],
        ]);
        $user = User::query()->where('email', strtolower($validated['email']))->firstOrFail();
        $manager->create($user, $request->user(), filled($validated['payout_details'] ?? null)
            ? ['instructions' => $validated['payout_details']]
            : null);

        return back()->with('status', 'Sales partner activated. Their stable referral link is ready.');
    }

    public function update(
        Request $request,
        SalesPartnerProfile $partner,
        ManageSalesPartner $manager,
    ): RedirectResponse {
        $validated = $request->validate([
            'status' => ['required', Rule::enum(SalesPartnerStatus::class)],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);
        $manager->setStatus($partner, SalesPartnerStatus::from($validated['status']), $request->user(), $validated['reason'] ?? null);

        return back()->with('status', 'Partner status updated and audited.');
    }
}
