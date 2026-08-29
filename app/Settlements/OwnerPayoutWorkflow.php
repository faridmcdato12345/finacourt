<?php

namespace App\Settlements;

use App\Enums\OwnerPayoutStatus;
use App\Enums\OwnerSettlementEntryType;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\OwnerPayout;
use App\Models\OwnerPayoutProfile;
use App\Models\OwnerSettlementEntry;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OwnerPayoutWorkflow
{
    public function create(Organization $organization, User $admin, string $currency, string $throughDate): OwnerPayout
    {
        $this->ensureAdmin($admin);

        $cutoff = CarbonImmutable::parse($throughDate, 'UTC')->endOfDay()->min(now());

        return $this->prepare(
            $organization,
            $admin,
            $currency,
            $cutoff,
            CarbonImmutable::parse($throughDate)->toDateString(),
        );
    }

    public function request(Organization $organization, User $owner, string $currency = 'PHP'): OwnerPayout
    {
        $this->ensureOwner($organization, $owner);
        $now = CarbonImmutable::now('UTC');

        return $this->prepare(
            $organization,
            $owner,
            $currency,
            $now,
            $now->toDateString(),
            requestedByOwner: true,
        );
    }

    private function prepare(
        Organization $organization,
        User $actor,
        string $currency,
        CarbonImmutable $cutoff,
        string $periodEndedAt,
        bool $requestedByOwner = false,
    ): OwnerPayout {
        return DB::transaction(function () use (
            $organization,
            $actor,
            $currency,
            $cutoff,
            $periodEndedAt,
            $requestedByOwner,
        ): OwnerPayout {
            // Locking the tenant serializes owner requests and administrator-
            // prepared batches. The selected ledger rows are locked below as
            // a second guard against assigning one earning twice.
            Organization::query()->whereKey($organization)->lockForUpdate()->firstOrFail();

            if ($requestedByOwner) {
                $hasOpenPayout = OwnerPayout::query()
                    ->where('organization_id', $organization->getKey())
                    ->whereIn('status', [OwnerPayoutStatus::Pending, OwnerPayoutStatus::Approved])
                    ->lockForUpdate()
                    ->exists();

                if ($hasOpenPayout) {
                    throw ValidationException::withMessages([
                        'payout' => 'A payout is already being reviewed or prepared for this court account.',
                    ]);
                }
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
            $entries = OwnerSettlementEntry::query()
                ->where('organization_id', $organization->getKey())
                ->where('currency', $currency)
                ->whereNull('owner_payout_id')
                ->where('available_at', '<=', now())
                ->where('occurred_at', '<=', $cutoff)
                ->orderBy('occurred_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $amountCents = $entries->sum(fn (OwnerSettlementEntry $entry): int => $this->cents($entry->amount));

            if ($entries->isEmpty() || $amountCents <= 0) {
                throw ValidationException::withMessages([
                    $requestedByOwner ? 'payout' : 'period_ended_at' => 'There are no ready online court earnings to pay out yet.',
                ]);
            }

            $minimumCents = (int) config('settlements.minimum_request_amount_centavos', 50000);

            if ($requestedByOwner && $amountCents < $minimumCents) {
                throw ValidationException::withMessages([
                    'payout' => 'Your ready balance must reach '.$this->money($minimumCents).' PHP before you can request a payout.',
                ]);
            }

            $payout = OwnerPayout::query()->create([
                'organization_id' => $organization->getKey(),
                'reference' => 'OWNER-'.Str::upper(Str::ulid()->toBase32()),
                'status' => OwnerPayoutStatus::Pending,
                'amount' => $this->money($amountCents),
                'currency' => $currency,
                'period_started_at' => $entries->min('occurred_at')->toDateString(),
                'period_ended_at' => $periodEndedAt,
                'destination_snapshot' => $profile->destinationSnapshot(),
                'created_by_user_id' => $actor->getKey(),
                'requested_by_user_id' => $requestedByOwner ? $actor->getKey() : null,
                'requested_at' => $requestedByOwner ? now() : null,
            ]);

            OwnerSettlementEntry::query()
                ->whereKey($entries->modelKeys())
                ->update(['owner_payout_id' => $payout->getKey()]);

            $this->event(
                $payout,
                $actor,
                $requestedByOwner ? 'requested' : 'created',
                null,
                OwnerPayoutStatus::Pending,
                metadata: [
                    'entry_count' => $entries->count(),
                    'amount' => $payout->amount,
                    'origin' => $requestedByOwner ? 'court_owner' : 'platform_admin',
                ],
            );

            return $payout->refresh();
        }, 5);
    }

    public function approve(OwnerPayout $payout, User $admin): OwnerPayout
    {
        return $this->transition($payout, $admin, [OwnerPayoutStatus::Pending], OwnerPayoutStatus::Approved, [
            'approved_by_user_id' => $admin->getKey(),
            'approved_at' => now(),
        ]);
    }

    public function markSent(OwnerPayout $payout, User $admin, string $reference, ?string $note = null): OwnerPayout
    {
        return $this->transition($payout, $admin, [OwnerPayoutStatus::Approved], OwnerPayoutStatus::Sent, [
            'external_reference' => $reference,
            'note' => $note,
            'sent_by_user_id' => $admin->getKey(),
            'sent_at' => now(),
        ], 'sent', $note, ['external_reference' => $reference]);
    }

    public function markFailed(OwnerPayout $payout, User $admin, string $reason): OwnerPayout
    {
        return $this->release($payout, $admin, OwnerPayoutStatus::Failed, 'failed', $reason, 'failed_at');
    }

    public function cancel(OwnerPayout $payout, User $admin, string $reason): OwnerPayout
    {
        return $this->release($payout, $admin, OwnerPayoutStatus::Cancelled, 'cancelled', $reason, 'cancelled_at');
    }

    public function reverse(OwnerPayout $payout, User $admin, string $reason): OwnerPayout
    {
        $this->ensureAdmin($admin);

        return DB::transaction(function () use ($payout, $admin, $reason): OwnerPayout {
            $locked = OwnerPayout::query()->whereKey($payout)->lockForUpdate()->firstOrFail();
            $this->ensureStatus($locked, [OwnerPayoutStatus::Sent]);
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
                    'amount' => $locked->amount,
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

        if ($this->cents($amount) === 0) {
            throw ValidationException::withMessages(['amount' => 'The correction amount cannot be zero.']);
        }

        return OwnerSettlementEntry::query()->create([
            'organization_id' => $organization->getKey(),
            'type' => OwnerSettlementEntryType::AdminAdjustment,
            'amount' => $this->money($this->cents($amount)),
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

    private function release(
        OwnerPayout $payout,
        User $admin,
        OwnerPayoutStatus $to,
        string $action,
        string $reason,
        string $timestampColumn,
    ): OwnerPayout {
        $this->ensureAdmin($admin);

        return DB::transaction(function () use ($payout, $admin, $to, $action, $reason, $timestampColumn): OwnerPayout {
            $locked = OwnerPayout::query()->whereKey($payout)->lockForUpdate()->firstOrFail();
            $this->ensureStatus($locked, [OwnerPayoutStatus::Pending, OwnerPayoutStatus::Approved]);
            $previous = $locked->status;
            $locked->update(['status' => $to, $timestampColumn => now(), 'note' => $reason]);
            $locked->entries()->update(['owner_payout_id' => null]);
            $this->event($locked, $admin, $action, $previous, $to, $reason);

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
            ->where('role', 'owner')
            ->exists(), 403);
    }

    private function event(
        OwnerPayout $payout,
        User $actor,
        string $action,
        ?OwnerPayoutStatus $from,
        OwnerPayoutStatus $to,
        ?string $note = null,
        ?array $metadata = null,
    ): void {
        $payout->events()->create([
            'organization_id' => $payout->organization_id,
            'actor_user_id' => $actor->getKey(),
            'action' => $action,
            'from_status' => $from,
            'to_status' => $to,
            'note' => $note,
            'metadata' => $metadata,
        ]);
    }

    private function cents(string|int|float|null $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    private function money(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
