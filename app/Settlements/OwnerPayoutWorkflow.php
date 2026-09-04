<?php

namespace App\Settlements;

use App\Enums\MembershipRole;
use App\Enums\OwnerPayoutStatus;
use App\Enums\OwnerPayoutType;
use App\Enums\OwnerSettlementEntryType;
use App\Enums\PaymentStatus;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\OwnerPayout;
use App\Models\OwnerPayoutProfile;
use App\Models\OwnerSettlementEntry;
use App\Models\User;
use App\Payouts\Contracts\PayoutProvider;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OwnerPayoutWorkflow
{
    public function __construct(
        private readonly OwnerBalanceService $balances,
        private readonly OwnerPayoutFeeCalculator $fees,
        private readonly OwnerPayoutSchedule $schedule,
        private readonly OwnerPayoutNotifier $notifications,
        private readonly PayoutProvider $provider,
    ) {}

    public function create(Organization $organization, User $admin, string $currency, string $throughDate): OwnerPayout
    {
        $this->ensureAdmin($admin);
        $cutoff = CarbonImmutable::parse($throughDate, $this->schedule->timezone())
            ->endOfDay()
            ->utc()
            ->min(now());

        return $this->prepare(
            organization: $organization,
            actor: $admin,
            currency: $currency,
            cutoff: $cutoff,
            periodEndedAt: CarbonImmutable::parse($throughDate)->toDateString(),
            type: OwnerPayoutType::Scheduled,
            minimumCents: 1,
            scheduledFor: CarbonImmutable::parse($throughDate, $this->schedule->timezone()),
        );
    }

    public function request(Organization $organization, User $owner, string $currency = 'PHP'): OwnerPayout
    {
        $this->ensureOwner($organization, $owner);

        if (! config('settlements.enabled') || ! config('settlements.early.enabled')) {
            throw ValidationException::withMessages(['payout' => 'Early payouts are not available right now.']);
        }

        $now = CarbonImmutable::now('UTC');

        return $this->prepare(
            organization: $organization,
            actor: $owner,
            currency: $currency,
            cutoff: $now,
            periodEndedAt: $now->setTimezone($this->schedule->timezone())->toDateString(),
            type: OwnerPayoutType::Early,
            minimumCents: (int) config('settlements.early.minimum_centavos', 100),
            requestedByOwner: true,
        );
    }

    public function schedule(Organization $organization, CarbonInterface $cycleDate, ?string $currency = null): ?OwnerPayout
    {
        if (! config('settlements.enabled')
            || ! config('settlements.scheduled.enabled')
            || ! $this->schedule->isScheduledDate($cycleDate)) {
            return null;
        }

        $currency ??= (string) config('settlements.currency', 'PHP');
        $localDate = $this->schedule->localDate($cycleDate);
        $cycleKey = $this->schedule->cycleKey($organization->getKey(), $currency, $localDate);
        $cutoff = CarbonImmutable::instance($cycleDate)->utc()->min(now());

        try {
            return $this->prepare(
                organization: $organization,
                actor: null,
                currency: $currency,
                cutoff: $cutoff,
                periodEndedAt: $localDate->toDateString(),
                type: OwnerPayoutType::Scheduled,
                minimumCents: (int) config('settlements.scheduled.minimum_centavos', 100),
                scheduledFor: $localDate,
                cycleKey: $cycleKey,
            );
        } catch (ValidationException) {
            // Missing payout details, a small carry-forward balance, or no
            // eligible balance is a normal scheduler skip, not a failed job.
            return null;
        }
    }

    private function prepare(
        Organization $organization,
        ?User $actor,
        string $currency,
        CarbonImmutable $cutoff,
        string $periodEndedAt,
        OwnerPayoutType $type,
        int $minimumCents,
        bool $requestedByOwner = false,
        ?CarbonInterface $scheduledFor = null,
        ?string $cycleKey = null,
    ): OwnerPayout {
        if (! config('settlements.enabled')) {
            throw ValidationException::withMessages(['payout' => 'Owner payouts are not available right now.']);
        }

        $payout = DB::transaction(function () use (
            $organization,
            $actor,
            $currency,
            $cutoff,
            $periodEndedAt,
            $type,
            $minimumCents,
            $requestedByOwner,
            $scheduledFor,
            $cycleKey,
        ): OwnerPayout {
            // Tenant locking serializes scheduled, early, and administrator
            // payout creation. Entry locks then make reservation atomic.
            Organization::query()->whereKey($organization)->lockForUpdate()->firstOrFail();

            if ($cycleKey !== null && OwnerPayout::query()->where('cycle_key', $cycleKey)->exists()) {
                throw ValidationException::withMessages(['payout' => 'This payout cycle was already prepared.']);
            }

            if ($requestedByOwner && OwnerPayout::query()
                ->where('organization_id', $organization->getKey())
                ->where('payout_type', OwnerPayoutType::Early)
                ->whereIn('status', [
                    OwnerPayoutStatus::Pending,
                    OwnerPayoutStatus::Approved,
                    OwnerPayoutStatus::Processing,
                ])
                ->exists()) {
                throw ValidationException::withMessages([
                    'payout' => 'An early payout is already queued or being processed for this account.',
                ]);
            }

            $profile = OwnerPayoutProfile::query()
                ->where('organization_id', $organization->getKey())
                ->where('is_active', true)
                ->lockForUpdate()
                ->first();

            if (! $profile) {
                throw ValidationException::withMessages([
                    $requestedByOwner ? 'payout' : 'organization_id' => 'Active bank or GCash details are required before a payout can be prepared.',
                ]);
            }

            $currency = strtoupper($currency);
            $entries = $this->balances
                ->availableEntriesQuery($organization->getKey(), $currency, $cutoff)
                ->where('occurred_at', '<=', $cutoff)
                ->orderBy('occurred_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $grossCents = $entries->sum(
                fn (OwnerSettlementEntry $entry): int => Money::cents($entry->amount),
            );

            if ($entries->isEmpty() || $grossCents <= 0) {
                throw ValidationException::withMessages([
                    $requestedByOwner ? 'payout' : 'period_ended_at' => 'There are no completed and cleared online court earnings to pay out yet.',
                ]);
            }

            if ($grossCents < $minimumCents) {
                throw ValidationException::withMessages([
                    $requestedByOwner ? 'payout' : 'period_ended_at' => 'The available balance must reach '.Money::format($minimumCents).' '.$currency.'.',
                ]);
            }

            $quote = $this->fees->quote($type, $grossCents);

            if ($quote['net_cents'] <= 0) {
                throw ValidationException::withMessages([
                    $requestedByOwner ? 'payout' : 'period_ended_at' => 'The payout amount must be greater than its configured transfer fee.',
                ]);
            }

            $payout = OwnerPayout::query()->create([
                'organization_id' => $organization->getKey(),
                'reference' => 'OWNER-'.Str::upper(Str::ulid()->toBase32()),
                'payout_type' => $type,
                'status' => OwnerPayoutStatus::Pending,
                'amount' => Money::format($grossCents),
                'gross_amount' => Money::format($grossCents),
                'payout_fee' => Money::format($quote['fee_cents']),
                'net_amount' => Money::format($quote['net_cents']),
                'fee_payer' => $quote['fee_payer'],
                'currency' => $currency,
                'provider' => $this->provider->key(),
                'cycle_key' => $cycleKey,
                'period_started_at' => $entries->min('occurred_at')->toDateString(),
                'period_ended_at' => $periodEndedAt,
                'scheduled_for' => $scheduledFor?->toDateString(),
                'destination_snapshot' => $profile->destinationSnapshot(),
                'created_by_user_id' => $actor?->getKey(),
                'requested_by_user_id' => $requestedByOwner ? $actor?->getKey() : null,
                'requested_at' => $requestedByOwner ? now() : null,
                'metadata' => ['provider_automation' => $this->provider->supportsAutomaticTransfers()],
            ]);

            OwnerSettlementEntry::query()
                ->whereKey($entries->modelKeys())
                ->whereNull('owner_payout_id')
                ->update(['owner_payout_id' => $payout->getKey()]);

            $this->event(
                $payout,
                $actor,
                $requestedByOwner ? 'requested_early' : 'scheduled',
                null,
                OwnerPayoutStatus::Pending,
                metadata: [
                    'entry_count' => $entries->count(),
                    'gross_amount' => $payout->gross_amount,
                    'payout_fee' => $payout->payout_fee,
                    'net_amount' => $payout->net_amount,
                    'fee_payer' => $payout->fee_payer,
                    'origin' => $requestedByOwner ? 'court_owner' : ($actor ? 'platform_admin' : 'scheduler'),
                ],
            );

            return $payout->refresh();
        }, 5);

        $this->notifications->queued($payout);

        return $payout;
    }

    public function approve(OwnerPayout $payout, User $admin): OwnerPayout
    {
        $approved = $this->transition($payout, $admin, [OwnerPayoutStatus::Pending], OwnerPayoutStatus::Approved, [
            'approved_by_user_id' => $admin->getKey(),
            'approved_at' => now(),
        ]);
        $this->notifications->approved($approved);

        return $approved;
    }

    public function startProcessing(OwnerPayout $payout, User $admin): OwnerPayout
    {
        $this->ensureAdmin($admin);

        [$processing, $transitioned] = DB::transaction(function () use ($payout, $admin): array {
            $locked = OwnerPayout::query()->whereKey($payout)->lockForUpdate()->firstOrFail();

            if ($locked->status === OwnerPayoutStatus::Processing) {
                return [$locked, false];
            }

            $this->ensureStatus($locked, [OwnerPayoutStatus::Pending, OwnerPayoutStatus::Approved]);
            $previous = $locked->status;
            $locked->update([
                'status' => OwnerPayoutStatus::Processing,
                'processing_started_at' => now(),
            ]);
            $this->event($locked, $admin, 'processing', $previous, OwnerPayoutStatus::Processing, metadata: [
                'provider' => $locked->provider,
                'automation' => $this->provider->supportsAutomaticTransfers(),
            ]);

            return [$locked->refresh(), true];
        }, 5);

        if ($transitioned) {
            $this->notifications->processing($processing);
        }

        return $processing;
    }

    public function markPaid(
        OwnerPayout $payout,
        User $admin,
        string $reference,
        string $paidAmount,
        CarbonInterface $paidAt,
        ?string $note = null,
    ): OwnerPayout {
        $this->ensureAdmin($admin);
        $reference = trim($reference);
        $paidCents = Money::cents($paidAmount);
        $reconciliationKey = hash('sha256', $this->provider->key().'|'.$reference);

        $completed = DB::transaction(function () use ($payout, $admin, $reference, $reconciliationKey, $paidCents, $paidAt, $note): OwnerPayout {
            $locked = OwnerPayout::query()->whereKey($payout)->lockForUpdate()->firstOrFail();

            if (in_array($locked->status, [OwnerPayoutStatus::Paid, OwnerPayoutStatus::Sent], true)) {
                if ($locked->external_reference === $reference
                    && Money::cents($locked->paid_amount ?? $locked->net_amount) === $paidCents) {
                    return $locked;
                }

                throw ValidationException::withMessages(['payout' => 'This payout is already paid with different reconciliation details.']);
            }

            $this->ensureStatus($locked, [OwnerPayoutStatus::Processing]);

            if (OwnerPayout::query()
                ->where('reconciliation_key', $reconciliationKey)
                ->whereKeyNot($locked->getKey())
                ->exists()) {
                throw ValidationException::withMessages([
                    'external_reference' => 'This transfer reference is already reconciled to another payout.',
                ]);
            }

            if (Money::cents($locked->net_amount) !== $paidCents) {
                throw ValidationException::withMessages([
                    'paid_amount' => 'The transferred amount must exactly match the payout net amount of '.$locked->net_amount.' '.$locked->currency.'.',
                ]);
            }

            $invalidBookingEarning = $locked->entries()
                ->where('type', OwnerSettlementEntryType::BookingPayment)
                ->where(function ($query): void {
                    $query->whereDoesntHave('payment', fn ($payment) => $payment
                        ->where('status', PaymentStatus::Paid)
                        ->where('requires_review', false))
                        ->orWhereDoesntHave('booking', fn ($booking) => $booking
                            ->where('status', 'confirmed')
                            ->whereNull('cancelled_at'));
                })
                ->exists();

            if ($invalidBookingEarning) {
                throw ValidationException::withMessages([
                    'payout' => 'An included booking was refunded, cancelled, or placed under review. Mark this payout failed and reconcile it before transferring money.',
                ]);
            }

            $locked->update([
                'status' => OwnerPayoutStatus::Paid,
                'external_reference' => $reference,
                'reconciliation_key' => $reconciliationKey,
                'note' => $note,
                'sent_by_user_id' => $admin->getKey(),
                'sent_at' => $paidAt,
                'paid_at' => $paidAt,
                'paid_amount' => Money::format($paidCents),
                'failure_code' => null,
                'failure_message' => null,
            ]);
            $this->event($locked, $admin, 'admin_marked_paid', OwnerPayoutStatus::Processing, OwnerPayoutStatus::Paid, $note, [
                'external_reference' => $reference,
                'paid_amount' => Money::format($paidCents),
                'paid_at' => $paidAt->toIso8601String(),
            ]);

            return $locked->refresh();
        }, 5);

        $this->notifications->paid($completed);

        return $completed;
    }

    /** @deprecated Use markPaid after startProcessing so transfer reconciliation is explicit. */
    public function markSent(OwnerPayout $payout, User $admin, string $reference, ?string $note = null): OwnerPayout
    {
        $processing = $this->startProcessing($payout, $admin);

        return $this->markPaid($processing, $admin, $reference, $processing->net_amount, now(), $note);
    }

    public function markFailed(OwnerPayout $payout, User $admin, string $reason, ?string $code = null): OwnerPayout
    {
        $failed = $this->release(
            $payout,
            $admin,
            OwnerPayoutStatus::Failed,
            'failed',
            $reason,
            'failed_at',
            [OwnerPayoutStatus::Pending, OwnerPayoutStatus::Approved, OwnerPayoutStatus::Processing],
            ['failure_code' => $code, 'failure_message' => $reason],
        );
        $this->notifications->failed($failed);

        return $failed;
    }

    public function cancel(OwnerPayout $payout, User $admin, string $reason): OwnerPayout
    {
        return $this->release(
            $payout,
            $admin,
            OwnerPayoutStatus::Cancelled,
            'cancelled',
            $reason,
            'cancelled_at',
            [OwnerPayoutStatus::Pending, OwnerPayoutStatus::Approved],
        );
    }

    public function reverse(OwnerPayout $payout, User $admin, string $reason): OwnerPayout
    {
        $this->ensureAdmin($admin);

        return DB::transaction(function () use ($payout, $admin, $reason): OwnerPayout {
            $locked = OwnerPayout::query()->whereKey($payout)->lockForUpdate()->firstOrFail();
            $this->ensureStatus($locked, [OwnerPayoutStatus::Paid, OwnerPayoutStatus::Sent]);
            $from = $locked->status;

            $locked->update([
                'status' => OwnerPayoutStatus::Reversed,
                'reversed_at' => now(),
                'note' => $reason,
            ]);

            OwnerSettlementEntry::query()->firstOrCreate(
                ['source_key' => "owner-payout:{$locked->getKey()}:reversed"],
                [
                    'organization_id' => $locked->organization_id,
                    'type' => OwnerSettlementEntryType::PayoutReversal,
                    'amount' => $locked->net_amount,
                    'currency' => $locked->currency,
                    'description' => "Returned payout {$locked->reference}",
                    'occurred_at' => now(),
                    'available_at' => now(),
                    'metadata' => ['owner_payout_reference' => $locked->reference, 'reason' => $reason],
                    'created_by_user_id' => $admin->getKey(),
                ],
            );

            $this->event($locked, $admin, 'reversed', $from, OwnerPayoutStatus::Reversed, $reason);

            return $locked->refresh();
        }, 5);
    }

    public function adjust(Organization $organization, User $admin, string $amount, string $currency, string $reason): OwnerSettlementEntry
    {
        $this->ensureAdmin($admin);
        $amountCents = Money::cents($amount);

        if ($amountCents === 0) {
            throw ValidationException::withMessages(['amount' => 'The correction amount cannot be zero.']);
        }

        return OwnerSettlementEntry::query()->create([
            'organization_id' => $organization->getKey(),
            'type' => OwnerSettlementEntryType::AdminAdjustment,
            'amount' => Money::format($amountCents),
            'currency' => strtoupper($currency),
            'source_key' => 'owner-adjustment:'.Str::ulid(),
            'description' => $reason,
            'occurred_at' => now(),
            'available_at' => now(),
            'metadata' => ['reason' => $reason],
            'created_by_user_id' => $admin->getKey(),
        ]);
    }

    /** @param array<int, OwnerPayoutStatus> $from */
    private function transition(
        OwnerPayout $payout,
        User $admin,
        array $from,
        OwnerPayoutStatus $to,
        array $attributes,
        string $action = 'approved',
        ?string $note = null,
        ?array $metadata = null,
    ): OwnerPayout {
        $this->ensureAdmin($admin);

        return DB::transaction(function () use ($payout, $admin, $from, $to, $attributes, $action, $note, $metadata): OwnerPayout {
            $locked = OwnerPayout::query()->whereKey($payout)->lockForUpdate()->firstOrFail();
            $this->ensureStatus($locked, $from);
            $previous = $locked->status;
            $locked->update(['status' => $to, ...$attributes]);
            $this->event($locked, $admin, $action, $previous, $to, $note, $metadata);

            return $locked->refresh();
        }, 5);
    }

    /** @param array<int, OwnerPayoutStatus> $allowed */
    private function release(
        OwnerPayout $payout,
        User $admin,
        OwnerPayoutStatus $to,
        string $action,
        string $reason,
        string $timestampColumn,
        array $allowed,
        array $extra = [],
    ): OwnerPayout {
        $this->ensureAdmin($admin);

        return DB::transaction(function () use ($payout, $admin, $to, $action, $reason, $timestampColumn, $allowed, $extra): OwnerPayout {
            $locked = OwnerPayout::query()->whereKey($payout)->lockForUpdate()->firstOrFail();
            $this->ensureStatus($locked, $allowed);
            $previous = $locked->status;
            $locked->update([
                'status' => $to,
                $timestampColumn => now(),
                'note' => $reason,
                ...$extra,
            ]);
            $releasedEntries = $locked->entries()->count();
            $locked->entries()->update(['owner_payout_id' => null]);
            $this->event($locked, $admin, $action, $previous, $to, $reason, [
                'released_entry_count' => $releasedEntries,
                'funds_released' => true,
            ]);

            return $locked->refresh();
        }, 5);
    }

    /** @param array<int, OwnerPayoutStatus> $allowed */
    private function ensureStatus(OwnerPayout $payout, array $allowed): void
    {
        if (! in_array($payout->status, $allowed, true)) {
            throw ValidationException::withMessages([
                'payout' => "Payout {$payout->reference} cannot be changed from {$payout->status->label()}.",
            ]);
        }
    }

    private function ensureAdmin(User $user): void
    {
        abort_unless($user->is_platform_admin, 403);
    }

    private function ensureOwner(Organization $organization, User $user): void
    {
        abort_unless(Membership::query()
            ->where('organization_id', $organization->getKey())
            ->where('user_id', $user->getKey())
            ->where('role', MembershipRole::Owner)
            ->exists(), 403);
    }

    private function event(
        OwnerPayout $payout,
        ?User $actor,
        string $action,
        ?OwnerPayoutStatus $from,
        OwnerPayoutStatus $to,
        ?string $note = null,
        ?array $metadata = null,
    ): void {
        $payout->events()->create([
            'organization_id' => $payout->organization_id,
            'actor_user_id' => $actor?->getKey(),
            'action' => $action,
            'from_status' => $from,
            'to_status' => $to,
            'note' => $note,
            'metadata' => $metadata,
        ]);
    }
}
