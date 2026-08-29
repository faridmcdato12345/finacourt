<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\OwnerPayout;
use App\Settlements\OwnerPayoutWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OwnerPayoutController extends Controller
{
    public function store(Request $request, OwnerPayoutWorkflow $workflow): RedirectResponse
    {
        $validated = $request->validate([
            'organization_id' => ['required', 'integer', Rule::exists('organizations', 'id')],
            'currency' => ['required', 'string', 'size:3'],
            'period_ended_at' => ['required', 'date', 'before_or_equal:today'],
        ]);
        $organization = Organization::query()->findOrFail($validated['organization_id']);
        $payout = $workflow->create(
            $organization,
            $request->user(),
            $validated['currency'],
            $validated['period_ended_at'],
        );

        return back()->with('status', "Payout {$payout->reference} was prepared. Review it before approving.");
    }

    public function approve(Request $request, OwnerPayout $payout, OwnerPayoutWorkflow $workflow): RedirectResponse
    {
        $workflow->approve($payout, $request->user());

        return back()->with('status', 'The payout is approved and ready to send outside FinACourt.');
    }

    public function send(Request $request, OwnerPayout $payout, OwnerPayoutWorkflow $workflow): RedirectResponse
    {
        $validated = $request->validate([
            'external_reference' => ['required', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);
        $workflow->markSent($payout, $request->user(), $validated['external_reference'], $validated['note'] ?? null);

        return back()->with('status', 'The external transfer reference was recorded and the payout is marked sent.');
    }

    public function fail(Request $request, OwnerPayout $payout, OwnerPayoutWorkflow $workflow): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $workflow->markFailed($payout, $request->user(), $validated['reason']);

        return back()->with('status', 'The payout could not be sent. Its earnings are available for a new payout batch.');
    }

    public function cancel(Request $request, OwnerPayout $payout, OwnerPayoutWorkflow $workflow): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $workflow->cancel($payout, $request->user(), $validated['reason']);

        return back()->with('status', 'The payout was cancelled and its earnings were released.');
    }

    public function reverse(Request $request, OwnerPayout $payout, OwnerPayoutWorkflow $workflow): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $workflow->reverse($payout, $request->user(), $validated['reason']);

        return back()->with('status', 'The returned payout was recorded. The amount is owed again in a future payout.');
    }

    public function adjust(Request $request, OwnerPayoutWorkflow $workflow): RedirectResponse
    {
        $validated = $request->validate([
            'organization_id' => ['required', 'integer', Rule::exists('organizations', 'id')],
            'amount' => ['required', 'numeric', 'between:-99999999.99,99999999.99', 'not_in:0,0.00'],
            'currency' => ['required', 'string', 'size:3'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);
        $organization = Organization::query()->findOrFail($validated['organization_id']);
        $workflow->adjust($organization, $request->user(), (string) $validated['amount'], $validated['currency'], $validated['reason']);

        return back()->with('status', 'The correction was added as a separate earnings entry. Existing history was not changed.');
    }
}
