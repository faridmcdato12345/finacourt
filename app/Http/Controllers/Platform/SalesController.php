<?php

namespace App\Http\Controllers\Platform;

use App\Enums\CommissionEntryStatus;
use App\Enums\MembershipRole;
use App\Http\Controllers\Controller;
use App\Models\CommissionEntry;
use App\Models\CommissionRule;
use App\Models\Organization;
use App\Models\PartnerAuditEvent;
use App\Models\PartnerPayout;
use App\Models\SalesLead;
use App\Models\SalesPartnerProfile;
use Inertia\Inertia;
use Inertia\Response;

class SalesController extends Controller
{
    public function __invoke(): Response
    {
        $partners = SalesPartnerProfile::query()
            ->with('user:id,name,email')
            ->withCount(['leads', 'attributions as activated_venues_count' => fn ($query) => $query->whereNotNull('activated_at')])
            ->latest()
            ->get();
        $commissionTotals = CommissionEntry::query()
            ->selectRaw('sales_partner_profile_id, status, COALESCE(SUM(amount), 0) as total')
            ->groupBy('sales_partner_profile_id', 'status')
            ->get()
            ->groupBy('sales_partner_profile_id');

        return Inertia::render('Platform/Sales/Index', [
            'partners' => $partners->map(function ($partner) use ($commissionTotals) {
                $totals = $commissionTotals->get($partner->getKey(), collect())->mapWithKeys(
                    fn ($row) => [$row->getRawOriginal('status') => $row->total],
                );

                return [
                    'id' => $partner->getKey(),
                    'name' => $partner->user->name,
                    'email' => $partner->user->email,
                    'status' => $partner->status->value,
                    'referral_code' => $partner->referral_code,
                    'leads' => $partner->leads_count,
                    'activated_venues' => $partner->activated_venues_count,
                    'pending' => $totals[CommissionEntryStatus::Pending->value] ?? '0.00',
                    'available' => $totals[CommissionEntryStatus::Available->value] ?? '0.00',
                ];
            }),
            'leads' => SalesLead::query()->with(['partner.user:id,name', 'organization:id,name', 'venue:id,name'])
                ->latest()->limit(100)->get()->map(fn ($lead) => [
                    'id' => $lead->getKey(),
                    'business_name' => $lead->business_name,
                    'contact_person' => $lead->contact_person,
                    'city' => $lead->city,
                    'partner_id' => $lead->sales_partner_profile_id,
                    'partner_name' => $lead->partner->user->name,
                    'status' => $lead->status->value,
                    'status_label' => $lead->status->label(),
                    'conflict_status' => $lead->conflict_status->value,
                    'duplicate_of_lead_id' => $lead->duplicate_of_lead_id,
                    'protection_expires_at' => $lead->protection_expires_at?->toDateString(),
                    'organization' => $lead->organization?->name,
                    'venue' => $lead->venue?->name,
                ]),
            'rules' => CommissionRule::query()->latest()->get()->map(fn ($rule) => [
                'id' => $rule->getKey(),
                'name' => $rule->name,
                'trigger' => $rule->trigger->value,
                'trigger_label' => $rule->trigger->label(),
                'amount' => $rule->fixed_amount,
                'currency' => $rule->currency,
                'is_active' => $rule->is_active,
                'effective_from' => $rule->effective_from?->toDateString(),
                'effective_until' => $rule->effective_until?->toDateString(),
            ]),
            'commissions' => CommissionEntry::query()->with(['partner.user:id,name', 'lead:id,business_name'])
                ->latest()->limit(100)->get()->map(fn ($entry) => [
                    'id' => $entry->getKey(),
                    'partner' => $entry->partner->user->name,
                    'lead' => $entry->lead?->business_name,
                    'source' => $entry->source_type,
                    'amount' => $entry->amount,
                    'currency' => $entry->currency,
                    'status' => $entry->status->value,
                    'reason' => $entry->reason,
                    'created_at' => $entry->created_at->toDateString(),
                ]),
            'payouts' => PartnerPayout::query()->with('partner.user:id,name')->latest()->limit(100)->get()->map(fn ($payout) => [
                'id' => $payout->getKey(),
                'partner' => $payout->partner->user->name,
                'amount' => $payout->amount,
                'currency' => $payout->currency,
                'status' => $payout->status->value,
                'period_started_at' => $payout->period_started_at->toDateString(),
                'period_ended_at' => $payout->period_ended_at->toDateString(),
                'reference' => $payout->payment_reference,
            ]),
            'organizations' => Organization::query()
                ->with(['venues:id,organization_id,name', 'memberships' => fn ($query) => $query->where('role', MembershipRole::Owner)->with('user:id,name,email')])
                ->orderBy('name')->limit(100)->get()->map(fn ($organization) => [
                    'id' => $organization->getKey(),
                    'name' => $organization->name,
                    'venues' => $organization->venues->map->only(['id', 'name']),
                    'owners' => $organization->memberships->map(fn ($membership) => [
                        'id' => $membership->user_id,
                        'name' => $membership->user->name,
                        'email' => $membership->user->email,
                    ]),
                ]),
            'audit' => PartnerAuditEvent::query()->latest('id')->limit(30)->get()->map(fn ($event) => [
                'id' => $event->getKey(),
                'action' => $event->action,
                'partner_id' => $event->sales_partner_profile_id,
                'lead_id' => $event->sales_lead_id,
                'commission_id' => $event->commission_entry_id,
                'payout_id' => $event->partner_payout_id,
                'created_at' => $event->created_at?->toIso8601String(),
            ]),
        ]);
    }
}
