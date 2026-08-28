<?php

namespace App\SalesPartners;

use App\Models\CommissionEntry;
use App\Models\PartnerAuditEvent;
use App\Models\PartnerPayout;
use App\Models\SalesLead;
use App\Models\SalesPartnerProfile;
use App\Models\User;

class PartnerAudit
{
    /** @param array<string, mixed> $metadata */
    public function record(
        string $action,
        ?User $actor = null,
        ?SalesPartnerProfile $partner = null,
        ?SalesLead $lead = null,
        ?CommissionEntry $entry = null,
        ?PartnerPayout $payout = null,
        array $metadata = [],
    ): PartnerAuditEvent {
        return PartnerAuditEvent::query()->create([
            'actor_user_id' => $actor?->getKey(),
            'sales_partner_profile_id' => $partner?->getKey() ?? $lead?->sales_partner_profile_id ?? $entry?->sales_partner_profile_id ?? $payout?->sales_partner_profile_id,
            'sales_lead_id' => $lead?->getKey(),
            'commission_entry_id' => $entry?->getKey(),
            'partner_payout_id' => $payout?->getKey(),
            'action' => $action,
            'metadata' => $metadata === [] ? null : $metadata,
        ]);
    }
}
