<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\OwnerPayout;
use App\Settlements\OwnerPayoutWorkflow;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OwnerPayoutController extends Controller
{
    public function store(Request $request, OwnerPayoutWorkflow $workflow): RedirectResponse
    {
        $validated = $request->validate([
            'organization_id' => ['required', 'integer', Rule::exists('organizations', 'id')],
            'currency' => ['required', 'string', 'size:3'],
            'period_ended_at' => ['required', 'date'],
        ]);
        $timezone = (string) config('settlements.timezone', 'Asia/Manila');
        $periodEndedAt = CarbonImmutable::parse($validated['period_ended_at'], $timezone)->startOfDay();

        if ($periodEndedAt->isAfter(CarbonImmutable::now($timezone)->startOfDay())) {
            throw ValidationException::withMessages([
                'period_ended_at' => 'The payout cutoff cannot be after today in the settlement timezone.',
            ]);
        }

        $organization = Organization::query()->findOrFail($validated['organization_id']);
        $payout = $workflow->create(
            $organization,
            $request->user(),
            $validated['currency'],
            $periodEndedAt->toDateString(),
        );

        return back()->with('status', "Payout {$payout->reference} was prepared. Review it before approving.");
    }

    public function approve(Request $request, OwnerPayout $payout, OwnerPayoutWorkflow $workflow): RedirectResponse
    {
        $workflow->approve($payout, $request->user());

        return back()->with('status', 'The payout is approved and ready to send outside FinACourt.');
    }

    public function process(Request $request, OwnerPayout $payout, OwnerPayoutWorkflow $workflow): RedirectResponse
    {
        $workflow->startProcessing($payout, $request->user());

        return back()->with('status', 'The payout is now processing. Complete the external transfer, then record its reconciliation details.');
    }

    public function send(Request $request, OwnerPayout $payout, OwnerPayoutWorkflow $workflow): RedirectResponse
    {
        $validated = $request->validate([
            'external_reference' => ['required', 'string', 'max:255'],
            'paid_amount' => ['required', 'decimal:0,2', 'gt:0'],
            'paid_at' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);
        $paidAt = CarbonImmutable::parse(
            $validated['paid_at'],
            (string) config('settlements.timezone', 'Asia/Manila'),
        )->utc();

        if ($paidAt->isFuture()) {
            throw ValidationException::withMessages([
                'paid_at' => 'The paid timestamp cannot be in the future.',
            ]);
        }

        $workflow->markPaid(
            $payout,
            $request->user(),
            $validated['external_reference'],
            (string) $validated['paid_amount'],
            $paidAt,
            $validated['note'] ?? null,
        );

        return back()->with('status', 'The transfer was reconciled and the payout is marked paid.');
    }

    public function fail(Request $request, OwnerPayout $payout, OwnerPayoutWorkflow $workflow): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
            'failure_code' => ['nullable', 'string', 'max:100'],
        ]);
        $workflow->markFailed($payout, $request->user(), $validated['reason'], $validated['failure_code'] ?? null);

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
            'amount' => ['required', 'decimal:0,2', 'between:-99999999.99,99999999.99', 'not_in:0,0.00'],
            'currency' => ['required', 'string', 'size:3'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);
        $organization = Organization::query()->findOrFail($validated['organization_id']);
        $workflow->adjust($organization, $request->user(), (string) $validated['amount'], $validated['currency'], $validated['reason']);

        return back()->with('status', 'The correction was added as a separate earnings entry. Existing history was not changed.');
    }
}
