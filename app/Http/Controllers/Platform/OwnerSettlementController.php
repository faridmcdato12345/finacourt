<?php

namespace App\Http\Controllers\Platform;

use App\Enums\OwnerPayoutStatus;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\OwnerPayout;
use App\Models\OwnerSettlementEntry;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OwnerSettlementController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $organizations = Organization::query()
            ->with('payoutProfile')
            ->orderBy('name')
            ->get();
        $now = now();

        $available = OwnerSettlementEntry::query()
            ->selectRaw('organization_id, currency, SUM(amount) AS total')
            ->whereNull('owner_payout_id')
            ->where('available_at', '<=', $now)
            ->groupBy('organization_id', 'currency')
            ->get()
            ->keyBy(fn ($row) => $row->organization_id.'-'.$row->currency);
        $waiting = OwnerSettlementEntry::query()
            ->selectRaw('organization_id, currency, SUM(amount) AS total')
            ->whereNull('owner_payout_id')
            ->where('available_at', '>', $now)
            ->groupBy('organization_id', 'currency')
            ->get()
            ->keyBy(fn ($row) => $row->organization_id.'-'.$row->currency);

        return Inertia::render('Platform/Settlements/Index', [
            'organizations' => $organizations->map(function (Organization $organization) use ($available, $waiting) {
                $profile = $organization->payoutProfile;

                return [
                    'id' => $organization->getKey(),
                    'name' => $organization->name,
                    'ready' => $this->money($available->get($organization->getKey().'-PHP')?->total),
                    'waiting' => $this->money($waiting->get($organization->getKey().'-PHP')?->total),
                    'payment_details_ready' => $profile?->is_active === true,
                    'payment_method' => $profile?->method->label(),
                    'payment_summary' => $profile ? $this->profileSummary($profile->details, $profile->method->value) : null,
                ];
            }),
            'payouts' => OwnerPayout::query()
                ->with(['organization:id,name', 'requestedBy:id,name,email'])
                ->withCount('entries')
                ->latest()
                ->limit(100)
                ->get()
                ->map(fn (OwnerPayout $payout) => [
                    'id' => $payout->getKey(),
                    'organization' => $payout->organization->name,
                    'reference' => $payout->reference,
                    'status' => $payout->status->value,
                    'status_label' => $payout->status->label(),
                    'amount' => $payout->amount,
                    'currency' => $payout->currency,
                    'period' => $payout->period_started_at->format('M j, Y').' – '.$payout->period_ended_at->format('M j, Y'),
                    'destination' => $payout->destination_snapshot,
                    'entries_count' => $payout->entries_count,
                    'external_reference' => $payout->external_reference,
                    'note' => $payout->note,
                    'requested_by_owner' => $payout->requested_at !== null,
                    'requested_by' => $payout->requestedBy?->name,
                    'requested_at' => $payout->requested_at?->toDateTimeString(),
                    'can_approve' => $payout->status === OwnerPayoutStatus::Pending,
                    'can_send' => $payout->status === OwnerPayoutStatus::Approved,
                    'can_fail' => in_array($payout->status, [OwnerPayoutStatus::Pending, OwnerPayoutStatus::Approved], true),
                    'can_cancel' => in_array($payout->status, [OwnerPayoutStatus::Pending, OwnerPayoutStatus::Approved], true),
                    'can_reverse' => $payout->status === OwnerPayoutStatus::Sent,
                ]),
            'recentEntries' => OwnerSettlementEntry::query()
                ->with(['organization:id,name', 'booking:id,reference'])
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
                    'ready' => $entry->owner_payout_id === null && $entry->available_at->lessThanOrEqualTo($now),
                ]),
            'defaults' => [
                'through_date' => now()->toDateString(),
                'currency' => 'PHP',
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

    private function money(mixed $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }
}
