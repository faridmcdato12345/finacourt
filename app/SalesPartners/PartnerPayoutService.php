<?php

namespace App\SalesPartners;

use App\Enums\CommissionEntryStatus;
use App\Enums\PartnerPayoutStatus;
use App\Models\CommissionEntry;
use App\Models\PartnerPayout;
use App\Models\SalesPartnerProfile;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PartnerPayoutService
{
    public function __construct(private readonly PartnerAudit $audit) {}

    public function create(
        SalesPartnerProfile $partner,
        CarbonImmutable $from,
        CarbonImmutable $until,
        User $admin,
        ?string $note = null,
    ): PartnerPayout {
        $this->assertAdmin($admin);

        if ($from->gt($until)) {
            throw ValidationException::withMessages(['period_ended_at' => 'Payout end date must be on or after its start date.']);
        }

        return DB::transaction(function () use ($partner, $from, $until, $admin, $note): PartnerPayout {
            $entries = CommissionEntry::query()
                ->where('sales_partner_profile_id', $partner->getKey())
                ->where('status', CommissionEntryStatus::Available)
                ->whereNull('partner_payout_id')
                ->whereDate('available_at', '>=', $from)
                ->whereDate('available_at', '<=', $until)
                ->lockForUpdate()
                ->get();
            $total = $entries->sum(fn (CommissionEntry $entry) => (float) $entry->amount);

            if ($entries->isEmpty() || $total <= 0) {
                throw ValidationException::withMessages(['payout' => 'No positive available commission exists in this period.']);
            }

            $payout = PartnerPayout::query()->create([
                'sales_partner_profile_id' => $partner->getKey(),
                'period_started_at' => $from->toDateString(),
                'period_ended_at' => $until->toDateString(),
                'amount' => number_format($total, 2, '.', ''),
                'currency' => config('sales_partners.currency'),
                'status' => PartnerPayoutStatus::Pending,
                'note' => $note,
            ]);
            $entries->each(fn (CommissionEntry $entry) => $entry->update(['partner_payout_id' => $payout->getKey()]));
            $this->audit->record('payout.created', $admin, payout: $payout, metadata: ['entry_ids' => $entries->modelKeys()]);

            return $payout;
        }, 5);
    }

    public function approve(PartnerPayout $payout, User $admin): PartnerPayout
    {
        $this->assertAdmin($admin);

        if ($payout->status !== PartnerPayoutStatus::Pending) {
            throw ValidationException::withMessages(['payout' => 'Only a pending payout can be approved.']);
        }

        $payout->update([
            'status' => PartnerPayoutStatus::Approved,
            'approved_by_user_id' => $admin->getKey(),
            'approved_at' => now('UTC'),
        ]);
        $this->audit->record('payout.approved', $admin, payout: $payout);

        return $payout->refresh();
    }

    public function markPaid(PartnerPayout $payout, User $admin, string $reference, ?string $note = null): PartnerPayout
    {
        $this->assertAdmin($admin);

        if ($payout->status !== PartnerPayoutStatus::Approved) {
            throw ValidationException::withMessages(['payout' => 'Only an approved payout can be marked paid.']);
        }

        return DB::transaction(function () use ($payout, $admin, $reference, $note): PartnerPayout {
            $payout = PartnerPayout::query()->whereKey($payout->getKey())->lockForUpdate()->firstOrFail();
            $payout->entries()->where('status', CommissionEntryStatus::Available)->get()
                ->each(fn (CommissionEntry $entry) => $entry->update(['status' => CommissionEntryStatus::Paid]));
            $payout->update([
                'status' => PartnerPayoutStatus::Paid,
                'paid_by_user_id' => $admin->getKey(),
                'paid_at' => now('UTC'),
                'payment_reference' => $reference,
                'note' => $note ?? $payout->note,
            ]);
            $this->audit->record('payout.paid', $admin, payout: $payout, metadata: ['reference' => $reference]);

            return $payout->refresh();
        }, 5);
    }

    public function cancel(PartnerPayout $payout, User $admin, string $reason): PartnerPayout
    {
        $this->assertAdmin($admin);

        if (! in_array($payout->status, [PartnerPayoutStatus::Pending, PartnerPayoutStatus::Approved], true)) {
            throw ValidationException::withMessages(['payout' => 'A paid or cancelled payout cannot be cancelled.']);
        }

        return DB::transaction(function () use ($payout, $admin, $reason): PartnerPayout {
            $payout->entries()->where('status', CommissionEntryStatus::Available)->get()
                ->each(fn (CommissionEntry $entry) => $entry->update(['partner_payout_id' => null]));
            $payout->update(['status' => PartnerPayoutStatus::Cancelled, 'note' => $reason]);
            $this->audit->record('payout.cancelled', $admin, payout: $payout, metadata: ['reason' => $reason]);

            return $payout->refresh();
        }, 5);
    }

    private function assertAdmin(User $user): void
    {
        if (! $user->is_platform_admin) {
            abort(403);
        }
    }
}
