<?php

namespace App\SalesPartners;

use App\Enums\LeadConflictStatus;
use App\Enums\MembershipRole;
use App\Enums\SalesLeadStatus;
use App\Models\Organization;
use App\Models\SalesLead;
use App\Models\SalesPartnerAttribution;
use App\Models\SalesPartnerProfile;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LeadManager
{
    public function __construct(
        private readonly PartnerAudit $audit,
        private readonly CommissionLedger $ledger,
    ) {}

    /** @param array<string, mixed> $data */
    public function create(SalesPartnerProfile $partner, User $actor, array $data): SalesLead
    {
        $this->assertCanRepresent($partner, $actor);
        $this->assertNotSelfReferral($partner, $data['contact_method'], $data['contact_value']);
        $hash = $this->dedupeHash($data['business_name'], $data['city'], $data['contact_value']);
        $existing = SalesLead::query()->where('dedupe_hash', $hash)->latest('id')->first();

        if ($existing?->sales_partner_profile_id === $partner->getKey() && ($existing->isProtected() || $existing->organization_id !== null)) {
            throw ValidationException::withMessages(['contact_value' => 'This lead is already registered to your account.']);
        }

        $disputed = $existing !== null && ($existing->isProtected() || $existing->organization_id !== null);
        $now = now('UTC');
        $lead = SalesLead::query()->create([
            'sales_partner_profile_id' => $partner->getKey(),
            'assigned_by_user_id' => $actor->getKey(),
            'business_name' => $data['business_name'],
            'contact_person' => $data['contact_person'],
            'contact_method' => $data['contact_method'],
            'contact_value' => $data['contact_value'],
            'dedupe_hash' => $hash,
            'city' => $data['city'],
            'notes' => $data['notes'] ?? null,
            'lead_source' => $data['lead_source'] ?? null,
            'status' => SalesLeadStatus::New,
            'conflict_status' => $disputed ? LeadConflictStatus::Disputed : LeadConflictStatus::Clear,
            'duplicate_of_lead_id' => $disputed ? $existing?->getKey() : null,
            'protection_started_at' => $disputed ? null : $now,
            'protection_expires_at' => $disputed ? null : $now->copy()->addDays((int) config('sales_partners.lead_protection_days')),
            'onboarding_data' => $data['onboarding_data'] ?? null,
            'status_changed_at' => $now,
        ]);
        $this->audit->record($disputed ? 'lead.duplicate_disputed' : 'lead.created', $actor, $partner, $lead, metadata: [
            'duplicate_of_lead_id' => $lead->duplicate_of_lead_id,
        ]);

        return $lead;
    }

    /** @param array<string, mixed> $data */
    public function updateOnboarding(SalesLead $lead, User $actor, array $data): SalesLead
    {
        $this->assertCanRepresent($lead->partner, $actor);

        if ($lead->conflict_status === LeadConflictStatus::Disputed) {
            throw ValidationException::withMessages(['lead' => 'A disputed lead must be resolved by a platform administrator first.']);
        }

        $lead->update([
            'notes' => $data['notes'] ?? $lead->notes,
            'onboarding_data' => array_filter($data['onboarding_data'] ?? [], fn ($value) => $value !== null && $value !== ''),
        ]);
        $this->audit->record('lead.onboarding_updated', $actor, lead: $lead);

        return $lead->refresh();
    }

    public function transition(SalesLead $lead, SalesLeadStatus $target, User $actor): SalesLead
    {
        $isAdmin = $actor->is_platform_admin;

        if (! $isAdmin) {
            $this->assertCanRepresent($lead->partner, $actor);
        }

        if ($lead->conflict_status === LeadConflictStatus::Disputed) {
            throw ValidationException::withMessages(['status' => 'Resolve the lead dispute before changing its lifecycle.']);
        }

        if (in_array($target, [SalesLeadStatus::Activated, SalesLeadStatus::Won], true) && ! $isAdmin) {
            abort(403);
        }

        if (! in_array($target, $lead->status->next(), true)) {
            throw ValidationException::withMessages([
                'status' => "Lead cannot move from {$lead->status->label()} to {$target->label()}.",
            ]);
        }

        if ($target === SalesLeadStatus::Activated) {
            throw ValidationException::withMessages(['status' => 'Use verified activation to link a real owner, organization, and venue.']);
        }

        $from = $lead->status;
        $lead->update([
            'status' => $target,
            'status_changed_at' => now('UTC'),
            'won_at' => $target === SalesLeadStatus::Won ? now('UTC') : $lead->won_at,
            'lost_at' => $target === SalesLeadStatus::Lost ? now('UTC') : $lead->lost_at,
            'expired_at' => $target === SalesLeadStatus::Expired ? now('UTC') : $lead->expired_at,
        ]);
        $this->audit->record('lead.status_changed', $actor, lead: $lead, metadata: [
            'from' => $from->value,
            'to' => $target->value,
        ]);

        if ($target === SalesLeadStatus::Won) {
            $this->ledger->awardActivation($lead->refresh(), $actor);
        }

        return $lead->refresh();
    }

    public function activate(
        SalesLead $lead,
        Organization $organization,
        Venue $venue,
        User $owner,
        User $admin,
    ): SalesLead {
        if (! $admin->is_platform_admin) {
            abort(403);
        }

        if ($lead->status !== SalesLeadStatus::Onboarding || $lead->conflict_status === LeadConflictStatus::Disputed) {
            throw ValidationException::withMessages(['status' => 'Only a clear onboarding lead can be verified as activated.']);
        }

        if ($venue->organization_id !== $organization->getKey()
            || ! $organization->memberships()->where('user_id', $owner->getKey())->where('role', MembershipRole::Owner)->exists()) {
            throw ValidationException::withMessages(['organization_id' => 'Venue, organization, and verified owner must belong together.']);
        }

        return DB::transaction(function () use ($lead, $organization, $venue, $owner, $admin): SalesLead {
            $lead = SalesLead::query()->whereKey($lead->getKey())->lockForUpdate()->firstOrFail();
            $existing = SalesPartnerAttribution::query()
                ->where(fn ($query) => $query->where('organization_id', $organization->getKey())->orWhere('venue_id', $venue->getKey()))
                ->lockForUpdate()
                ->first();

            if ($existing && $existing->sales_partner_profile_id !== $lead->sales_partner_profile_id) {
                throw ValidationException::withMessages(['organization_id' => 'This existing organization or venue is already attributed to another partner.']);
            }

            $attribution = $existing ?? SalesPartnerAttribution::query()->create([
                'sales_partner_profile_id' => $lead->sales_partner_profile_id,
                'sales_lead_id' => $lead->getKey(),
                'organization_id' => $organization->getKey(),
                'venue_id' => $venue->getKey(),
                'owner_user_id' => $owner->getKey(),
                'referral_code_snapshot' => $lead->partner->referral_code,
                'source' => 'assisted_onboarding',
                'attributed_at' => now('UTC'),
                'activated_at' => now('UTC'),
                'created_by_user_id' => $admin->getKey(),
            ]);

            if ($existing) {
                $attribution->update([
                    'sales_lead_id' => $attribution->sales_lead_id ?? $lead->getKey(),
                    'venue_id' => $attribution->venue_id ?? $venue->getKey(),
                    'activated_at' => $attribution->activated_at ?? now('UTC'),
                ]);
            }

            $lead->update([
                'organization_id' => $organization->getKey(),
                'venue_id' => $venue->getKey(),
                'owner_user_id' => $owner->getKey(),
                'status' => SalesLeadStatus::Activated,
                'activated_at' => now('UTC'),
                'status_changed_at' => now('UTC'),
            ]);
            $this->audit->record('lead.verified_activated', $admin, lead: $lead, metadata: [
                'organization_id' => $organization->getKey(),
                'venue_id' => $venue->getKey(),
                'owner_user_id' => $owner->getKey(),
                'attribution_id' => $attribution->getKey(),
            ]);

            return $lead->refresh();
        }, 5);
    }

    public function overrideProtection(
        SalesLead $lead,
        SalesPartnerProfile $partner,
        User $admin,
        string $reason,
    ): SalesLead {
        if (! $admin->is_platform_admin) {
            abort(403);
        }

        if ($lead->organization_id !== null || $lead->attribution()->exists()) {
            throw ValidationException::withMessages(['partner_id' => 'An activated attribution cannot be silently reassigned. Use a commission adjustment and audit note.']);
        }

        if (! $partner->isActive()) {
            throw ValidationException::withMessages(['partner_id' => 'Lead protection can only be assigned to an active partner.']);
        }

        $from = $lead->sales_partner_profile_id;
        $lead->update([
            'sales_partner_profile_id' => $partner->getKey(),
            'conflict_status' => LeadConflictStatus::Resolved,
            'protection_started_at' => now('UTC'),
            'protection_expires_at' => now('UTC')->addDays((int) config('sales_partners.lead_protection_days')),
        ]);
        $this->audit->record('lead.protection_overridden', $admin, $partner, $lead, metadata: [
            'from_partner_id' => $from,
            'to_partner_id' => $partner->getKey(),
            'reason' => $reason,
        ]);

        return $lead->refresh();
    }

    public function dedupeHash(string $business, string $city, string $contact): string
    {
        return hash('sha256', implode('|', [
            Str::lower(Str::squish($business)),
            Str::lower(Str::squish($contact)),
        ]));
    }

    private function assertCanRepresent(SalesPartnerProfile $partner, User $actor): void
    {
        if ($actor->getKey() !== $partner->user_id || ! $partner->isActive()) {
            abort(403);
        }
    }

    private function assertNotSelfReferral(SalesPartnerProfile $partner, string $method, string $contact): void
    {
        if ($method === 'email' && hash_equals(Str::lower($partner->user->email), Str::lower(Str::squish($contact)))) {
            throw ValidationException::withMessages(['contact_value' => 'A sales partner cannot register themselves as a lead.']);
        }
    }
}
