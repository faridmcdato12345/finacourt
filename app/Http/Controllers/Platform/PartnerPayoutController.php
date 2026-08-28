<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\PartnerPayout;
use App\Models\SalesPartnerProfile;
use App\SalesPartners\PartnerPayoutService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PartnerPayoutController extends Controller
{
    public function store(Request $request, PartnerPayoutService $service): RedirectResponse
    {
        $validated = $request->validate([
            'partner_id' => ['required', 'integer', 'exists:sales_partner_profiles,id'],
            'period_started_at' => ['required', 'date'],
            'period_ended_at' => ['required', 'date', 'after_or_equal:period_started_at'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);
        $service->create(
            SalesPartnerProfile::query()->findOrFail($validated['partner_id']),
            CarbonImmutable::parse($validated['period_started_at']),
            CarbonImmutable::parse($validated['period_ended_at']),
            $request->user(),
            $validated['note'] ?? null,
        );

        return back()->with('status', 'Manual payout batch created from unreserved available ledger entries.');
    }

    public function approve(Request $request, PartnerPayout $payout, PartnerPayoutService $service): RedirectResponse
    {
        $service->approve($payout, $request->user());

        return back()->with('status', 'Payout approved. No funds have been sent by the application.');
    }

    public function pay(Request $request, PartnerPayout $payout, PartnerPayoutService $service): RedirectResponse
    {
        $validated = $request->validate([
            'reference' => ['required', 'string', 'max:120'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);
        $service->markPaid($payout, $request->user(), $validated['reference'], $validated['note'] ?? null);

        return back()->with('status', 'External/manual payout reference recorded and included entries marked paid.');
    }

    public function cancel(Request $request, PartnerPayout $payout, PartnerPayoutService $service): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $service->cancel($payout, $request->user(), $validated['reason']);

        return back()->with('status', 'Payout cancelled; its available entries were released for a later batch.');
    }
}
