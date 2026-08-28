<?php

namespace App\Tenancy;

use App\Models\Membership;
use App\Models\Organization;
use LogicException;

class TenantContext
{
    private ?Membership $membership = null;

    private ?Organization $organization = null;

    public function set(Organization $organization, ?Membership $membership = null): void
    {
        $this->organization = $organization;
        $this->membership = $membership;
    }

    public function organization(): Organization
    {
        return $this->organization
            ?? throw new LogicException('No organization has been resolved for this request.');
    }

    public function membership(): ?Membership
    {
        return $this->membership;
    }

    public function hasOrganization(): bool
    {
        return $this->organization !== null;
    }
}
