<?php

namespace App\SalesPartners;

use App\Enums\SalesPartnerStatus;
use App\Models\SalesPartnerProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ManageSalesPartner
{
    public function __construct(private readonly PartnerAudit $audit) {}

    /** @param array<string, mixed>|null $payoutDetails */
    public function create(User $user, User $admin, ?array $payoutDetails = null): SalesPartnerProfile
    {
        if (! $admin->is_platform_admin) {
            abort(403);
        }

        if ($user->is_platform_admin || $user->memberships()->exists()) {
            throw ValidationException::withMessages([
                'email' => 'A platform administrator or tenant member cannot also become a sales partner.',
            ]);
        }

        if ($user->salesPartnerProfile()->exists()) {
            throw ValidationException::withMessages(['email' => 'This user already has a sales partner profile.']);
        }

        return DB::transaction(function () use ($user, $admin, $payoutDetails): SalesPartnerProfile {
            $profile = SalesPartnerProfile::query()->create([
                'user_id' => $user->getKey(),
                'public_id' => (string) Str::ulid(),
                'referral_code' => $this->uniqueCode(),
                'status' => SalesPartnerStatus::Active,
                'payout_details' => $payoutDetails,
                'activated_at' => now('UTC'),
                'created_by_user_id' => $admin->getKey(),
            ]);
            $this->audit->record('partner.created', $admin, $profile);

            return $profile;
        });
    }

    public function setStatus(
        SalesPartnerProfile $profile,
        SalesPartnerStatus $status,
        User $admin,
        ?string $reason = null,
    ): SalesPartnerProfile {
        if (! $admin->is_platform_admin) {
            abort(403);
        }

        if ($status === SalesPartnerStatus::Suspended && blank($reason)) {
            throw ValidationException::withMessages(['reason' => 'A suspension reason is required.']);
        }

        $from = $profile->status;
        $profile->update([
            'status' => $status,
            'activated_at' => $status === SalesPartnerStatus::Active ? ($profile->activated_at ?? now('UTC')) : $profile->activated_at,
            'suspended_at' => $status === SalesPartnerStatus::Suspended ? now('UTC') : null,
            'suspension_reason' => $status === SalesPartnerStatus::Suspended ? $reason : null,
        ]);
        $this->audit->record('partner.status_changed', $admin, $profile, metadata: [
            'from' => $from->value,
            'to' => $status->value,
            'reason' => $reason,
        ]);

        return $profile->refresh();
    }

    private function uniqueCode(): string
    {
        do {
            $code = 'REP-'.Str::upper(Str::random(10));
        } while (SalesPartnerProfile::query()->where('referral_code', $code)->exists());

        return $code;
    }
}
