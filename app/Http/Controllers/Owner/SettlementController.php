<?php

namespace App\Http\Controllers\Owner;

use App\Enums\MembershipRole;
use App\Enums\OwnerPayoutMethod;
use App\Enums\OwnerPayoutStatus;
use App\Enums\OwnerPayoutType;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\OwnerPayout;
use App\Models\OwnerPayoutProfile;
use App\Models\OwnerSettlementEntry;
use App\Settlements\OwnerBalanceService;
use App\Settlements\OwnerPayoutFeeCalculator;
use App\Settlements\OwnerPayoutSchedule;
use App\Support\Money;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettlementController extends Controller
{
    public function __invoke(
        Request $request,
        TenantContext $context,
        OwnerBalanceService $balances,
        OwnerPayoutFeeCalculator $fees,
        OwnerPayoutSchedule $schedule,
    ): Response {
        $this->ensureOwner($context);
        $organization = $context->organization();
        $currency = (string) config('settlements.currency', 'PHP');
        $now = now();
        $summary = $balances->balances($organization->getKey(), $currency, $now);
        $availableEntryIds = $balances
            ->availableEntriesQuery($organization->getKey(), $currency, $now)
            ->pluck('id')
            ->all();
        $profile = OwnerPayoutProfile::query()
            ->where('organization_id', $organization->getKey())
            ->first();
        $earlyMinimumCents = (int) config('settlements.early.minimum_centavos', 100);
        $scheduledMinimumCents = (int) config('settlements.scheduled.minimum_centavos', 100);
        $earlyQuote = $fees->quote(OwnerPayoutType::Early, max(0, $summary['available']));
        $openPayout = OwnerPayout::query()
            ->where('organization_id', $organization->getKey())
            ->whereIn('status', [
                OwnerPayoutStatus::Pending,
                OwnerPayoutStatus::Approved,
                OwnerPayoutStatus::Processing,
            ])
            ->latest('id')
            ->first();
        $nextFreePayout = $schedule->nextDate();

        return Inertia::render('Owner/Settlements/Index', [
            'summary' => collect($summary)->map(fn (int $amount): string => Money::format($amount))->all(),
            'schedule' => [
                'enabled' => (bool) config('settlements.enabled') && (bool) config('settlements.scheduled.enabled'),
                'next_date' => $nextFreePayout->toDateString(),
                'next_date_label' => $nextFreePayout->format('F j, Y'),
                'minimum_amount' => Money::format($scheduledMinimumCents),
                'will_carry_forward' => $summary['available'] > 0 && $summary['available'] < $scheduledMinimumCents,
                'clearing_hours' => (int) config('settlements.clearing_hours', 24),
            ],
            'earlyPayout' => [
                'enabled' => (bool) config('settlements.enabled') && (bool) config('settlements.early.enabled'),
                'minimum_amount' => Money::format($earlyMinimumCents),
                'gross_amount' => Money::format(max(0, $summary['available'])),
                'fee_amount' => Money::format($earlyQuote['fee_cents']),
                'net_amount' => Money::format($earlyQuote['net_cents']),
                'fee_payer' => $earlyQuote['fee_payer'],
                'can_request' => (bool) config('settlements.enabled')
                    && (bool) config('settlements.early.enabled')
                    && $profile?->is_active === true
                    && $openPayout === null
                    && $summary['available'] >= $earlyMinimumCents
                    && $earlyQuote['net_cents'] > 0,
                'unavailable_reason' => $this->requestUnavailableReason(
                    $profile,
                    $openPayout,
                    $summary['available'],
                    $earlyMinimumCents,
                    $earlyQuote['net_cents'],
                ),
                'open' => $openPayout ? [
                    'reference' => $openPayout->reference,
                    'type_label' => $openPayout->payout_type->label(),
                    'net_amount' => $openPayout->net_amount,
                    'status_label' => $openPayout->status->label(),
                    'requested_at' => $openPayout->requested_at?->toDateTimeString(),
                ] : null,
            ],
            'profile' => $profile ? [
                'method' => $profile->method->value,
                'method_label' => $profile->method->label(),
                'account_name' => $profile->account_name,
                'summary' => $this->profileSummary($profile),
                'is_active' => $profile->is_active,
                'updated_at' => $profile->updated_at?->toDateTimeString(),
            ] : null,
            'methods' => collect(OwnerPayoutMethod::cases())->map(fn ($method) => [
                'value' => $method->value,
                'label' => $method->label(),
            ])->all(),
            'payouts' => OwnerPayout::query()
                ->where('organization_id', $organization->getKey())
                ->withCount('entries')
                ->latest()
                ->limit(25)
                ->get()
                ->map(fn (OwnerPayout $payout) => [
                    'id' => $payout->getKey(),
                    'reference' => $payout->reference,
                    'type' => $payout->payout_type->value,
                    'type_label' => $payout->payout_type->label(),
                    'gross_amount' => $payout->gross_amount,
                    'fee_amount' => $payout->payout_fee,
                    'net_amount' => $payout->net_amount,
                    'fee_payer' => $payout->fee_payer,
                    'paid_amount' => $payout->paid_amount,
                    'currency' => $payout->currency,
                    'status' => $payout->status->value,
                    'status_label' => $payout->status->label(),
                    'status_reason' => $this->payoutStatusReason($payout),
                    'requested_at' => $payout->requested_at?->toDateTimeString(),
                    'scheduled_for' => $payout->scheduled_for?->format('M j, Y'),
                    'processing_started_at' => $payout->processing_started_at?->toDateTimeString(),
                    'paid_at' => $payout->paid_at?->toDateTimeString(),
                    'period' => $payout->period_started_at->format('M j, Y').' – '.$payout->period_ended_at->format('M j, Y'),
                    'external_reference' => $payout->external_reference,
                    'entries_count' => $payout->entries_count,
                ]),
            'entries' => OwnerSettlementEntry::query()
                ->where('organization_id', $organization->getKey())
                ->with(['booking:id,reference,venue_id', 'booking.venue:id,name', 'payment:id,status', 'payout:id,status,reference'])
                ->latest('occurred_at')
                ->limit(50)
                ->get()
                ->map(fn (OwnerSettlementEntry $entry) => [
                    'id' => $entry->getKey(),
                    'type' => $entry->type->value,
                    'type_label' => $entry->type->label(),
                    'description' => $entry->description,
                    'amount' => $entry->amount,
                    'currency' => $entry->currency,
                    'booking_reference' => $entry->booking?->reference,
                    'venue' => $entry->booking?->venue?->name,
                    'occurred_at' => $entry->occurred_at->toDateTimeString(),
                    'available_at' => $entry->available_at->toDateTimeString(),
                    'state_label' => $this->entryState($entry, $availableEntryIds),
                    'payout_reference' => $entry->payout?->reference,
                ]),
        ]);
    }

    private function payoutStatusReason(OwnerPayout $payout): ?string
    {
        return match ($payout->status) {
            OwnerPayoutStatus::Failed => $payout->failure_message ?: $payout->note,
            OwnerPayoutStatus::Cancelled, OwnerPayoutStatus::Reversed => $payout->note,
            default => null,
        };
    }

    private function ensureOwner(TenantContext $context): void
    {
        abort_unless($context->membership()?->role === MembershipRole::Owner, 403);
    }

    /** @param array<int, int> $availableEntryIds */
    private function entryState(OwnerSettlementEntry $entry, array $availableEntryIds): string
    {
        if ($entry->payout) {
            return $entry->payout->status->label();
        }

        if ($entry->payment?->status === PaymentStatus::Refunded) {
            return 'Refunded — not payable';
        }

        return in_array($entry->getKey(), $availableEntryIds, true)
            ? 'Available for payout'
            : 'Pending booking completion or clearing';
    }

    private function profileSummary(OwnerPayoutProfile $profile): string
    {
        $details = $profile->details;
        $lastDigits = match ($profile->method->value) {
            'bank_transfer' => $details['account_number'] ?? '',
            'gcash' => $details['mobile_number'] ?? '',
            default => '',
        };

        if ($lastDigits === '') {
            return 'Manual payment instructions saved';
        }

        return trim(($details['bank_name'] ?? $profile->method->label()).' · ending '.substr(preg_replace('/\D+/', '', $lastDigits), -4));
    }

    private function requestUnavailableReason(
        ?OwnerPayoutProfile $profile,
        ?OwnerPayout $openPayout,
        int $availableCents,
        int $minimumCents,
        int $netCents,
    ): ?string {
        if (! config('settlements.enabled') || ! config('settlements.early.enabled')) {
            return 'Early payouts are not available right now. Your free scheduled payout remains the default.';
        }

        if ($profile?->is_active !== true) {
            return 'Add and turn on your bank or GCash details first.';
        }

        if ($openPayout) {
            return 'A payout is already queued or processing.';
        }

        if ($availableCents < $minimumCents) {
            return 'Your available balance must reach '.Money::format($minimumCents).' PHP.';
        }

        if ($netCents <= 0) {
            return 'The available amount must be greater than the configured transfer fee.';
        }

        return null;
    }
}
