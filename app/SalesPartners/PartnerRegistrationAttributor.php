<?php

namespace App\SalesPartners;

use App\Analytics\TrafficAttribution;
use App\Enums\LeadConflictStatus;
use App\Enums\SalesLeadStatus;
use App\Models\Organization;
use App\Models\SalesLead;
use App\Models\SalesPartnerAttribution;
use App\Models\User;
use Illuminate\Http\Request;

class PartnerRegistrationAttributor
{
    public function __construct(
        private readonly TrafficAttribution $traffic,
        private readonly LeadManager $leads,
        private readonly PartnerAudit $audit,
    ) {}

    public function attribute(
        Request $request,
        User $owner,
        Organization $organization,
    ): ?SalesPartnerAttribution {
        $partner = $this->traffic->trustedSalesPartner($request);

        if ($partner === null || $partner->user_id === $owner->getKey()) {
            if ($partner?->user_id === $owner->getKey()) {
                $this->audit->record('referral.self_rejected', partner: $partner, metadata: ['owner_user_id' => $owner->getKey()]);
            }

            return null;
        }

        $hash = $this->leads->dedupeHash($organization->name, '', $owner->email);
        $existing = SalesLead::query()->where('dedupe_hash', $hash)->latest('id')->first();

        if ($existing !== null && ($existing->organization_id !== null || $existing->status === SalesLeadStatus::Won)) {
            $this->audit->record('referral.existing_customer_rejected', partner: $partner, lead: $existing, metadata: ['organization_id' => $organization->getKey()]);

            return null;
        }

        if ($existing?->isProtected() && $existing->partner->isActive()) {
            $partner = $existing->partner;
            $lead = $existing;
        } else {
            $now = now('UTC');
            $lead = SalesLead::query()->create([
                'sales_partner_profile_id' => $partner->getKey(),
                'business_name' => $organization->name,
                'contact_person' => $owner->name,
                'contact_method' => 'email',
                'contact_value' => $owner->email,
                'dedupe_hash' => $hash,
                'city' => 'Not provided',
                'lead_source' => 'referral_link',
                'status' => SalesLeadStatus::Onboarding,
                'conflict_status' => LeadConflictStatus::Clear,
                'protection_started_at' => $now,
                'protection_expires_at' => $now->copy()->addDays((int) config('sales_partners.lead_protection_days')),
                'status_changed_at' => $now,
            ]);
        }

        $lead->update([
            'organization_id' => $organization->getKey(),
            'owner_user_id' => $owner->getKey(),
            'status' => SalesLeadStatus::Onboarding,
            'status_changed_at' => now('UTC'),
        ]);
        $attribution = SalesPartnerAttribution::query()->create([
            'sales_partner_profile_id' => $partner->getKey(),
            'sales_lead_id' => $lead->getKey(),
            'organization_id' => $organization->getKey(),
            'owner_user_id' => $owner->getKey(),
            'referral_code_snapshot' => $partner->referral_code,
            'source' => 'referral_link',
            'attributed_at' => now('UTC'),
        ]);
        $this->audit->record('referral.owner_registered', partner: $partner, lead: $lead, metadata: [
            'organization_id' => $organization->getKey(),
            'attribution_id' => $attribution->getKey(),
        ]);

        return $attribution;
    }
}
