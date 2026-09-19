<?php

namespace App\Organizations;

use App\Models\Organization;
use App\Models\StaffInvitation;
use App\Models\User;
use App\Notifications\StaffInvitationNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class StaffInvitationManager
{
    private const EXPIRATION_DAYS = 7;

    public function __construct(private readonly StaffPermissionSet $permissionSet) {}

    /** @param array<int, mixed> $permissions */
    public function invite(Organization $organization, User $inviter, string $email, array $permissions): StaffInvitation
    {
        $token = bin2hex(random_bytes(32));
        $invitation = DB::transaction(function () use ($organization, $inviter, $email, $permissions, $token): StaffInvitation {
            $invitation = StaffInvitation::query()
                ->where('organization_id', $organization->getKey())
                ->where('email', $email)
                ->lockForUpdate()
                ->first() ?? new StaffInvitation([
                    'organization_id' => $organization->getKey(),
                    'email' => $email,
                ]);

            $invitation->fill([
                'permissions' => $this->permissionSet->normalize($permissions),
                'token_hash' => StaffInvitation::hashToken($token),
                'invited_by_user_id' => $inviter->getKey(),
                'expires_at' => now('UTC')->addDays(self::EXPIRATION_DAYS),
                'last_sent_at' => now('UTC'),
                'accepted_at' => null,
                'accepted_by_user_id' => null,
                'revoked_at' => null,
                'revoked_by_user_id' => null,
            ])->save();

            return $invitation;
        }, 5);

        $this->notify($invitation, $inviter, $token);

        return $invitation;
    }

    public function resend(StaffInvitation $invitation, User $inviter): StaffInvitation
    {
        $token = bin2hex(random_bytes(32));
        $invitation = DB::transaction(function () use ($invitation, $inviter, $token): StaffInvitation {
            $locked = StaffInvitation::query()->lockForUpdate()->findOrFail($invitation->getKey());

            if ($locked->accepted_at !== null || $locked->revoked_at !== null) {
                throw ValidationException::withMessages([
                    'invitation' => 'Only pending or expired invitations can be resent.',
                ]);
            }

            $locked->update([
                'token_hash' => StaffInvitation::hashToken($token),
                'invited_by_user_id' => $inviter->getKey(),
                'expires_at' => now('UTC')->addDays(self::EXPIRATION_DAYS),
                'last_sent_at' => now('UTC'),
            ]);

            return $locked;
        }, 5);

        $this->notify($invitation, $inviter, $token);

        return $invitation;
    }

    public function revoke(StaffInvitation $invitation, User $actor): void
    {
        DB::transaction(function () use ($invitation, $actor): void {
            $locked = StaffInvitation::query()->lockForUpdate()->findOrFail($invitation->getKey());

            if ($locked->accepted_at !== null) {
                throw ValidationException::withMessages([
                    'invitation' => 'An accepted invitation cannot be revoked. Manage the staff member instead.',
                ]);
            }

            $locked->update([
                'revoked_at' => now('UTC'),
                'revoked_by_user_id' => $actor->getKey(),
            ]);
        }, 5);
    }

    private function notify(StaffInvitation $invitation, User $inviter, string $token): void
    {
        $invitation->loadMissing('organization');
        $expiresAt = $invitation->expires_at
            ->setTimezone($invitation->organization->timezone)
            ->format('M j, Y g:i A T');

        Notification::route('mail', $invitation->email)->notify(new StaffInvitationNotification(
            organizationName: $invitation->organization->name,
            inviterName: $inviter->name,
            url: route('staff-invitations.show', ['token' => $token]),
            expiresAt: $expiresAt,
        ));
    }
}
