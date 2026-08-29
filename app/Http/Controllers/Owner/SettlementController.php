<?php

namespace App\Http\Controllers\Owner;

use App\Enums\MembershipRole;
use App\Enums\OwnerPayoutMethod;
use App\Enums\OwnerPayoutStatus;
use App\Http\Controllers\Controller;
use App\Models\OwnerPayout;
use App\Models\OwnerPayoutProfile;
use App\Models\OwnerSettlementEntry;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettlementController extends Controller
{
    public function __invoke(Request $request, TenantContext $context): Response
    {
        $this->ensureOwner($context);
        $organization = $context->organization();
        $now = now();
        $entries = OwnerSettlementEntry::query()
            ->where('organization_id', $organization->getKey());

        $profile = OwnerPayoutProfile::query()
            ->where('organization_id', $organization->getKey())
            ->first();
        $readyAmount = (clone $entries)
            ->whereNull('owner_payout_id')
            ->where('available_at', '<=', $now)
            ->sum('amount');
        $minimumRequestCents = (int) config('settlements.minimum_request_amount_centavos', 50000);
        $openPayout = OwnerPayout::query()
            ->where('organization_id', $organization->getKey())
            ->whereIn('status', [OwnerPayoutStatus::Pending, OwnerPayoutStatus::Approved])
            ->latest('id')
            ->first();

        return Inertia::render('Owner/Settlements/Index', [
            'summary' => [
                'ready' => $this->money($readyAmount),
                'waiting' => $this->money((clone $entries)->whereNull('owner_payout_id')->where('available_at', '>', $now)->sum('amount')),
                'being_prepared' => $this->money(OwnerPayout::query()
                    ->where('organization_id', $organization->getKey())
                    ->whereIn('status', [OwnerPayoutStatus::Pending, OwnerPayoutStatus::Approved])
                    ->sum('amount')),
                'sent' => $this->money(OwnerPayout::query()
                    ->where('organization_id', $organization->getKey())
                    ->where('status', OwnerPayoutStatus::Sent)
                    ->sum('amount')),
            ],
            'payoutRequest' => [
                'minimum_amount' => $this->money($minimumRequestCents / 100),
                'can_request' => $profile?->is_active === true
                    && $openPayout === null
                    && $this->cents($readyAmount) >= $minimumRequestCents,
                'unavailable_reason' => $this->requestUnavailableReason(
                    $profile,
                    $openPayout,
                    $this->cents($readyAmount),
                    $minimumRequestCents,
                ),
                'open' => $openPayout ? [
                    'reference' => $openPayout->reference,
                    'amount' => $openPayout->amount,
                    'status_label' => $openPayout->status->label(),
                    'requested_at' => $openPayout->requested_at?->toDateTimeString(),
                    'was_requested_by_owner' => $openPayout->requested_at !== null,
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
                ->latest()
                ->limit(25)
                ->get()
                ->map(fn (OwnerPayout $payout) => [
                    'id' => $payout->getKey(),
                    'reference' => $payout->reference,
                    'amount' => $payout->amount,
                    'currency' => $payout->currency,
                    'status' => $payout->status->value,
                    'status_label' => $payout->status->label(),
                    'requested_at' => $payout->requested_at?->toDateTimeString(),
                    'period' => $payout->period_started_at->format('M j, Y').' – '.$payout->period_ended_at->format('M j, Y'),
                    'sent_at' => $payout->sent_at?->toDateTimeString(),
                    'external_reference' => $payout->external_reference,
                ]),
            'entries' => OwnerSettlementEntry::query()
                ->where('organization_id', $organization->getKey())
                ->with(['booking:id,reference,venue_id', 'booking.venue:id,name', 'payout:id,status,reference'])
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
                    'state_label' => $this->entryState($entry, $now),
                    'payout_reference' => $entry->payout?->reference,
                ]),
        ]);
    }

    private function ensureOwner(TenantContext $context): void
    {
        abort_unless($context->membership()?->role === MembershipRole::Owner, 403);
    }

    private function entryState(OwnerSettlementEntry $entry, $now): string
    {
        if ($entry->payout) {
            return $entry->payout->status->label();
        }

        return $entry->available_at->isFuture() ? 'Waiting period' : 'Ready for the next payout';
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

    private function money(mixed $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }

    private function cents(mixed $amount): int
    {
        return (int) round((float) $amount * 100);
    }

    private function requestUnavailableReason(
        ?OwnerPayoutProfile $profile,
        ?OwnerPayout $openPayout,
        int $readyCents,
        int $minimumCents,
    ): ?string {
        if ($profile?->is_active !== true) {
            return 'Add and turn on your bank or GCash details first.';
        }

        if ($openPayout) {
            return 'A payout is already being reviewed or prepared.';
        }

        if ($readyCents < $minimumCents) {
            return 'Your ready balance must reach '.$this->money($minimumCents / 100).' PHP.';
        }

        return null;
    }
}
