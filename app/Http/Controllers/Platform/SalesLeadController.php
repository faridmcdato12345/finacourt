<?php

namespace App\Http\Controllers\Platform;

use App\Enums\SalesLeadStatus;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\SalesLead;
use App\Models\SalesPartnerProfile;
use App\Models\User;
use App\Models\Venue;
use App\SalesPartners\LeadManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SalesLeadController extends Controller
{
    public function transition(Request $request, SalesLead $lead, LeadManager $manager): RedirectResponse
    {
        $validated = $request->validate(['status' => ['required', Rule::enum(SalesLeadStatus::class)]]);
        $manager->transition($lead, SalesLeadStatus::from($validated['status']), $request->user());

        return back()->with('status', 'Lead lifecycle updated and audited.');
    }

    public function activate(Request $request, SalesLead $lead, LeadManager $manager): RedirectResponse
    {
        $validated = $request->validate([
            'organization_id' => ['required', 'integer', 'exists:organizations,id'],
            'venue_id' => ['required', 'integer', 'exists:venues,id'],
            'owner_user_id' => ['required', 'integer', 'exists:users,id'],
        ]);
        $manager->activate(
            $lead,
            Organization::query()->findOrFail($validated['organization_id']),
            Venue::query()->findOrFail($validated['venue_id']),
            User::query()->findOrFail($validated['owner_user_id']),
            $request->user(),
        );

        return back()->with('status', 'Real owner, organization, and venue verified. The lead is activated.');
    }

    public function override(Request $request, SalesLead $lead, LeadManager $manager): RedirectResponse
    {
        $validated = $request->validate([
            'partner_id' => ['required', 'integer', 'exists:sales_partner_profiles,id'],
            'reason' => ['required', 'string', 'max:500'],
        ]);
        $manager->overrideProtection(
            $lead,
            SalesPartnerProfile::query()->findOrFail($validated['partner_id']),
            $request->user(),
            $validated['reason'],
        );

        return back()->with('status', 'Lead protection override recorded in the audit history.');
    }
}
