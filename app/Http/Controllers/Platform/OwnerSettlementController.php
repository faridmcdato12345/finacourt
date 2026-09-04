<?php

namespace App\Http\Controllers\Platform;

use App\Enums\OwnerPayoutStatus;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\OwnerPayout;
use App\Models\OwnerSettlementEntry;
use App\Settlements\OwnerBalanceService;
use App\Settlements\OwnerPayoutSchedule;
use App\Support\Money;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OwnerSettlementController extends Controller
{
    public function __invoke(
        Request $request,
        OwnerBalanceService $balances,
        OwnerPayoutSchedule $schedule,
    ): Response {
        $organizations = Organization::query()
            ->with('payoutProfile')
            ->orderBy('name')
            ->get();
        $currency = (string) config('settlements.currency', 'PHP');
        $now = now();
        $availableEntryIds = [];

        $organizationRows = $organizations->map(function (Organization $organization) use (
            $balances,
            $currency,
            $now,
            &$availableEntryIds,
        ): array {
            $profile = $organization->payoutProfile;
            $totals = $balances->balances($organization->getKey(), $currency, $now);
            $availableEntryIds = [
                ...$availableEntryIds,
                ...$balances->availableEntriesQuery($organization->getKey(), $currency, $now)->pluck('id')->all(),
            ];

            return [
                'id' => $organization->getKey(),
                'name' => $organization->name,
                'available' => Money::format($totals['available']),
                'pending' => Money::format($totals['pending']),
                'processing' => Money::format($totals['processing']),
                'paid' => Money::format($totals['paid']),
                'payment_details_ready' => $profile?->is_active === true,
                'payment_method' => $profile?->method->label(),
                'payment_summary' => $profile ? $this->profileSummary($profile->details, $profile->method->value) : null,
            ];
        });

        return Inertia::render('Platform/Settlements/Index', [
            'organizations' => $organizationRows,
            'payouts' => OwnerPayout::query()
                ->with([
                    'organization:id,name',
                    'requestedBy:id,name,email',
                    'events.actor:id,name,email',
                ])
                ->withCount('entries')
                ->latest()
                ->limit(100)
                ->get()
                ->map(fn (OwnerPayout $payout) => [
                    'id' => $payout->getKey(),
                    'organization' => $payout->organization->name,
                    'reference' => $payout->reference,
                    'type' => $payout->payout_type->value,
                    'type_label' => $payout->payout_type->label(),
                    'status' => $payout->status->value,
                    'status_label' => $payout->status->label(),
                    'gross_amount' => $payout->gross_amount,
                    'fee_amount' => $payout->payout_fee,
                    'net_amount' => $payout->net_amount,
                    'fee_payer' => $payout->fee_payer,
                    'paid_amount' => $payout->paid_amount,
                    'currency' => $payout->currency,
                    'provider' => $payout->provider,
                    'period' => $payout->period_started_at->format('M j, Y').' – '.$payout->period_ended_at->format('M j, Y'),
                    'scheduled_for' => $payout->scheduled_for?->format('M j, Y'),
                    'destination' => $payout->destination_snapshot,
                    'entries_count' => $payout->entries_count,
                    'external_reference' => $payout->external_reference,
                    'note' => $payout->note,
                    'failure_code' => $payout->failure_code,
                    'failure_message' => $payout->failure_message,
                    'requested_by_owner' => $payout->payout_type->value === 'early',
                    'requested_by' => $payout->requestedBy?->name,
                    'requested_at' => $payout->requested_at?->toDateTimeString(),
                    'processing_started_at' => $payout->processing_started_at?->toDateTimeString(),
                    'paid_at' => $payout->paid_at?->toDateTimeString(),
                    'can_approve' => $payout->status === OwnerPayoutStatus::Pending,
                    'can_process' => in_array($payout->status, [OwnerPayoutStatus::Pending, OwnerPayoutStatus::Approved], true),
                    'can_mark_paid' => $payout->status === OwnerPayoutStatus::Processing,
                    'can_fail' => in_array($payout->status, [
                        OwnerPayoutStatus::Pending,
                        OwnerPayoutStatus::Approved,
                        OwnerPayoutStatus::Processing,
                    ], true),
                    'can_cancel' => in_array($payout->status, [OwnerPayoutStatus::Pending, OwnerPayoutStatus::Approved], true),
                    'can_reverse' => in_array($payout->status, [OwnerPayoutStatus::Paid, OwnerPayoutStatus::Sent], true),
                    'events' => $payout->events->map(fn ($event) => [
                        'action' => $event->action,
                        'actor' => $event->actor?->name ?? 'System scheduler',
                        'note' => $event->note,
                        'created_at' => $event->created_at?->toDateTimeString(),
                    ]),
                ]),
            'recentEntries' => OwnerSettlementEntry::query()
                ->with(['organization:id,name', 'booking:id,reference', 'payout:id,reference'])
                ->latest('occurred_at')
                ->limit(100)
                ->get()
                ->map(fn (OwnerSettlementEntry $entry) => [
                    'id' => $entry->getKey(),
                    'organization' => $entry->organization->name,
                    'type' => $entry->type->label(),
                    'description' => $entry->description,
                    'booking_reference' => $entry->booking?->reference,
                    'amount' => $entry->amount,
                    'currency' => $entry->currency,
                    'occurred_at' => $entry->occurred_at->toDateTimeString(),
                    'available' => in_array($entry->getKey(), $availableEntryIds, true),
                    'payout_reference' => $entry->payout?->reference,
                ]),
            'defaults' => [
                'through_date' => now($schedule->timezone())->toDateString(),
                'currency' => $currency,
                'paid_at' => now($schedule->timezone())->format('Y-m-d\TH:i'),
            ],
            'policy' => [
                'next_free_payout' => $schedule->nextDate()->format('F j, Y'),
                'provider' => 'Manual reconciliation',
            ],
        ]);
    }

    /** @param array<string, mixed> $details */
    private function profileSummary(array $details, string $method): string
    {
        $number = $method === 'bank_transfer'
            ? ($details['account_number'] ?? '')
            : ($details['mobile_number'] ?? '');

        return $number === '' ? 'Instructions saved' : 'Ending '.substr(preg_replace('/\D+/', '', $number), -4);
    }
}
