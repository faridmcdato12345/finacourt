<?php

namespace App\SalesPartners;

use App\Enums\CommissionCalculation;
use App\Enums\CommissionEntryStatus;
use App\Enums\CommissionRuleTrigger;
use App\Enums\SalesLeadStatus;
use App\Models\CommissionEntry;
use App\Models\CommissionRule;
use App\Models\SalesLead;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CommissionLedger
{
    public function __construct(private readonly PartnerAudit $audit) {}

    /** @return list<CommissionEntry> */
    public function awardActivation(SalesLead $lead, User $actor): array
    {
        if ($lead->status !== SalesLeadStatus::Won || ! $lead->partner->isActive()) {
            throw ValidationException::withMessages(['commission' => 'Only a won lead assigned to an active partner can earn activation commission.']);
        }

        $attribution = $lead->attribution()->first();

        if ($attribution === null || $attribution->venue_id === null || $attribution->activated_at === null) {
            throw ValidationException::withMessages(['commission' => 'A verified venue attribution is required before commission can be awarded.']);
        }

        $at = now('UTC');
        $rules = CommissionRule::query()
            ->where('trigger', CommissionRuleTrigger::VenueActivation)
            ->where('calculation', CommissionCalculation::Fixed)
            ->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('effective_from')->orWhereDate('effective_from', '<=', $at))
            ->where(fn ($query) => $query->whereNull('effective_until')->orWhereDate('effective_until', '>=', $at))
            ->get();
        $entries = [];

        foreach ($rules as $rule) {
            $key = "activation:{$rule->getKey()}:lead:{$lead->getKey()}";
            $entry = CommissionEntry::query()->firstOrCreate(
                ['idempotency_key' => $key],
                [
                    'sales_partner_profile_id' => $lead->sales_partner_profile_id,
                    'commission_rule_id' => $rule->getKey(),
                    'sales_lead_id' => $lead->getKey(),
                    'sales_partner_attribution_id' => $attribution->getKey(),
                    'source_type' => 'venue_activation',
                    'source_reference' => (string) $attribution->venue_id,
                    'amount' => $rule->fixed_amount,
                    'currency' => $rule->currency,
                    'status' => CommissionEntryStatus::Pending,
                    'reason' => "Verified activation: {$lead->business_name}",
                    'rule_snapshot' => [
                        'rule_id' => $rule->getKey(),
                        'name' => $rule->name,
                        'trigger' => $rule->trigger->value,
                        'calculation' => $rule->calculation->value,
                        'fixed_amount' => $rule->fixed_amount,
                        'currency' => $rule->currency,
                    ],
                ],
            );

            if ($entry->wasRecentlyCreated) {
                $this->audit->record('commission.created', $actor, lead: $lead, entry: $entry);
            }

            $entries[] = $entry;
        }

        return $entries;
    }

    public function approve(CommissionEntry $entry, User $admin): CommissionEntry
    {
        $this->assertAdmin($admin);

        if ($entry->status !== CommissionEntryStatus::Pending) {
            throw ValidationException::withMessages(['commission' => 'Only pending commission can be approved.']);
        }

        $entry->update([
            'status' => CommissionEntryStatus::Available,
            'available_at' => now('UTC'),
            'approved_by_user_id' => $admin->getKey(),
        ]);
        $this->audit->record('commission.approved', $admin, entry: $entry);

        return $entry->refresh();
    }

    public function adjust(
        int $partnerId,
        string $amount,
        string $reason,
        User $admin,
    ): CommissionEntry {
        $this->assertAdmin($admin);

        if ((float) $amount === 0.0) {
            throw ValidationException::withMessages(['amount' => 'An adjustment cannot be zero.']);
        }

        $entry = CommissionEntry::query()->create([
            'sales_partner_profile_id' => $partnerId,
            'source_type' => 'admin_adjustment',
            'idempotency_key' => 'adjustment:'.Str::uuid(),
            'amount' => $amount,
            'currency' => config('sales_partners.currency'),
            'status' => CommissionEntryStatus::Pending,
            'reason' => $reason,
            'rule_snapshot' => ['created_by_user_id' => $admin->getKey()],
        ]);
        $this->audit->record('commission.adjusted', $admin, entry: $entry, metadata: ['reason' => $reason]);

        return $entry;
    }

    public function reverse(CommissionEntry $entry, string $reason, User $admin): CommissionEntry
    {
        $this->assertAdmin($admin);

        if ($entry->status === CommissionEntryStatus::Reversed) {
            throw ValidationException::withMessages(['commission' => 'This commission is already reversed.']);
        }

        return DB::transaction(function () use ($entry, $reason, $admin): CommissionEntry {
            $entry = CommissionEntry::query()->whereKey($entry->getKey())->lockForUpdate()->firstOrFail();
            $wasPaid = $entry->status === CommissionEntryStatus::Paid;
            $entry->update([
                'status' => CommissionEntryStatus::Reversed,
                'reversed_at' => now('UTC'),
                'reversed_by_user_id' => $admin->getKey(),
            ]);

            if ($wasPaid) {
                $recovery = CommissionEntry::query()->firstOrCreate(
                    ['idempotency_key' => "reversal:{$entry->getKey()}"],
                    [
                        'sales_partner_profile_id' => $entry->sales_partner_profile_id,
                        'sales_lead_id' => $entry->sales_lead_id,
                        'sales_partner_attribution_id' => $entry->sales_partner_attribution_id,
                        'payment_id' => $entry->payment_id,
                        'reverses_entry_id' => $entry->getKey(),
                        'source_type' => 'reversal_recovery',
                        'source_reference' => (string) $entry->getKey(),
                        'amount' => str_starts_with($entry->amount, '-')
                            ? substr($entry->amount, 1)
                            : '-'.$entry->amount,
                        'currency' => $entry->currency,
                        'status' => CommissionEntryStatus::Available,
                        'reason' => $reason,
                        'available_at' => now('UTC'),
                        'approved_by_user_id' => $admin->getKey(),
                    ],
                );
                $this->audit->record('commission.recovery_created', $admin, entry: $recovery, metadata: ['reversed_entry_id' => $entry->getKey()]);
            }

            $this->audit->record('commission.reversed', $admin, entry: $entry, metadata: ['reason' => $reason, 'was_paid' => $wasPaid]);

            return $entry->refresh();
        }, 5);
    }

    private function assertAdmin(User $user): void
    {
        if (! $user->is_platform_admin) {
            abort(403);
        }
    }
}
