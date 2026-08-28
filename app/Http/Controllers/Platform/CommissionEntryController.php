<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\CommissionEntry;
use App\Models\SalesPartnerProfile;
use App\SalesPartners\CommissionLedger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CommissionEntryController extends Controller
{
    public function approve(Request $request, CommissionEntry $entry, CommissionLedger $ledger): RedirectResponse
    {
        $ledger->approve($entry, $request->user());

        return back()->with('status', 'Commission approved and available for a manual payout.');
    }

    public function adjust(Request $request, CommissionLedger $ledger): RedirectResponse
    {
        $validated = $request->validate([
            'partner_id' => ['required', 'integer', 'exists:sales_partner_profiles,id'],
            'amount' => ['required', 'numeric', 'between:-9999999999.99,9999999999.99', 'not_in:0,0.00'],
            'reason' => ['required', 'string', 'max:500'],
        ]);
        $partner = SalesPartnerProfile::query()->findOrFail($validated['partner_id']);
        $ledger->adjust($partner->getKey(), (string) $validated['amount'], $validated['reason'], $request->user());

        return back()->with('status', 'Append-only commission adjustment created as pending.');
    }

    public function reverse(Request $request, CommissionEntry $entry, CommissionLedger $ledger): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $ledger->reverse($entry, $validated['reason'], $request->user());

        return back()->with('status', 'Commission reversed with audit evidence. Paid commission creates a recovery adjustment.');
    }
}
