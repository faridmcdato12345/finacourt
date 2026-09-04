<?php

namespace App\Http\Controllers\Owner;

use App\Enums\MembershipRole;
use App\Http\Controllers\Controller;
use App\Models\OwnerPayout;
use App\Tenancy\TenantContext;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PayoutStatementController extends Controller
{
    public function __invoke(OwnerPayout $payout, TenantContext $context): StreamedResponse
    {
        abort_unless($context->membership()?->role === MembershipRole::Owner, 403);
        abort_unless($payout->organization_id === $context->organization()->getKey(), 404);

        return $this->download($payout);
    }

    private function download(OwnerPayout $payout): StreamedResponse
    {
        $payout->load(['organization:id,name', 'entries.booking:id,reference']);

        return response()->streamDownload(function () use ($payout): void {
            $stream = fopen('php://output', 'w');
            fputcsv($stream, ['FinACourt owner payout statement']);
            fputcsv($stream, ['Payout reference', $payout->reference]);
            fputcsv($stream, ['Court owner account', $payout->organization->name]);
            fputcsv($stream, ['Status', $payout->status->label()]);
            fputcsv($stream, ['Period', $payout->period_started_at->toDateString().' to '.$payout->period_ended_at->toDateString()]);
            fputcsv($stream, ['Payout type', $payout->payout_type->label()]);
            fputcsv($stream, ['Gross owner earnings', $payout->gross_amount, $payout->currency]);
            fputcsv($stream, ['Transfer fee', $payout->payout_fee, $payout->currency]);
            fputcsv($stream, ['Fee paid by', $payout->fee_payer === 'owner' ? 'Court owner' : 'FinACourt']);
            fputcsv($stream, ['Net payout', $payout->net_amount, $payout->currency]);
            fputcsv($stream, ['Paid amount', $payout->paid_amount ?: 'Not paid yet', $payout->currency]);
            fputcsv($stream, ['Paid at', $payout->paid_at?->toDateTimeString() ?: 'Not paid yet']);
            fputcsv($stream, ['Transfer reference', $payout->external_reference ?: 'Not paid yet']);
            fputcsv($stream, []);
            fputcsv($stream, ['Date', 'Type', 'Booking', 'Description', 'Amount', 'Currency']);

            foreach ($payout->entries as $entry) {
                fputcsv($stream, [
                    $entry->occurred_at->toDateTimeString(),
                    $entry->type->label(),
                    $entry->booking?->reference,
                    $entry->description,
                    $entry->amount,
                    $entry->currency,
                ]);
            }

            fclose($stream);
        }, "{$payout->reference}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
