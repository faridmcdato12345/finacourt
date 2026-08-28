<?php

namespace App\Http\Controllers\Partner;

use App\Enums\CommissionEntryStatus;
use App\Http\Controllers\Controller;
use App\Models\SalesPartnerProfile;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        /** @var SalesPartnerProfile $partner */
        $partner = $request->attributes->get('salesPartnerProfile');
        $totals = $partner->commissionEntries()
            ->selectRaw('status, COALESCE(SUM(amount), 0) as total')
            ->groupBy('status')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->getRawOriginal('status') => $row->total]);

        return Inertia::render('Partner/Dashboard', [
            'partner' => [
                'public_id' => $partner->public_id,
                'status' => $partner->status->value,
                'status_label' => $partner->status->label(),
                'referral_code' => $partner->referral_code,
                'referral_url' => route('partner.referral', $partner->referral_code),
                'qr_url' => route('partner.referral.qr', $partner->referral_code),
                'suspension_reason' => $partner->suspension_reason,
            ],
            'metrics' => [
                'leads' => $partner->leads()->count(),
                'activated_venues' => $partner->attributions()->whereNotNull('activated_at')->count(),
                'pending' => $totals[CommissionEntryStatus::Pending->value] ?? '0.00',
                'available' => $totals[CommissionEntryStatus::Available->value] ?? '0.00',
                'paid' => $partner->payouts()->where('status', 'paid')->sum('amount'),
            ],
            'leads' => $partner->leads()->latest()->limit(8)->get()->map(fn ($lead) => [
                'id' => $lead->getKey(),
                'business_name' => $lead->business_name,
                'city' => $lead->city,
                'status' => $lead->status->label(),
                'conflict' => $lead->conflict_status->value,
                'protection_expires_at' => $lead->protection_expires_at?->toDateString(),
            ]),
            'payouts' => $partner->payouts()->latest()->limit(8)->get()->map(fn ($payout) => [
                'id' => $payout->getKey(),
                'period' => $payout->period_started_at->toDateString().' – '.$payout->period_ended_at->toDateString(),
                'amount' => $payout->amount,
                'currency' => $payout->currency,
                'status' => $payout->status->value,
                'paid_at' => $payout->paid_at?->toDateString(),
                'reference' => $payout->payment_reference,
            ]),
        ]);
    }
}
