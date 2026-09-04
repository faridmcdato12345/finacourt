<?php

namespace App\Settlements;

use App\Enums\MembershipRole;
use App\Models\OwnerPayout;
use App\Models\User;
use App\Notifications\OwnerPayoutNotification;
use App\Notifications\PlatformOwnerPayoutRequestedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class OwnerPayoutNotifier
{
    public function queued(OwnerPayout $payout): void
    {
        $type = $payout->payout_type->value === 'early' ? 'early payout request' : 'free scheduled payout';

        $this->send(
            $payout,
            'owner_payout_queued',
            'Your payout is queued',
            "Your {$payout->currency} {$payout->net_amount} {$type} is queued for processing.",
        );

        if ($payout->payout_type->value === 'early' && $payout->requested_by_user_id !== null) {
            $this->sendEarlyRequestToPlatformAdministrators($payout);
        }
    }

    public function paid(OwnerPayout $payout): void
    {
        $this->send(
            $payout,
            'owner_payout_paid',
            'Your payout was sent',
            "Your {$payout->currency} {$payout->paid_amount} payout was marked paid after FinACourt recorded the completed transfer details.",
        );
    }

    public function approved(OwnerPayout $payout): void
    {
        $this->send(
            $payout,
            'owner_payout_approved',
            'Your payout was approved',
            "FinACourt approved your {$payout->currency} {$payout->net_amount} payout. It is ready to be processed.",
        );
    }

    public function processing(OwnerPayout $payout): void
    {
        $this->send(
            $payout,
            'owner_payout_processing',
            'Your payout is being processed',
            "FinACourt started processing your {$payout->currency} {$payout->net_amount} payout. We will notify you again after the transfer is confirmed.",
        );
    }

    public function failed(OwnerPayout $payout): void
    {
        $this->send(
            $payout,
            'owner_payout_failed',
            "We couldn't complete your payout",
            'The payout was not completed. Its eligible earnings were returned safely to your available balance.',
        );
    }

    private function send(OwnerPayout $payout, string $kind, string $title, string $message): void
    {
        $payoutId = $payout->getKey();

        DB::afterCommit(function () use ($payoutId, $kind, $title, $message): void {
            $fresh = OwnerPayout::query()->find($payoutId);

            if (! $fresh) {
                return;
            }

            $owners = User::query()
                ->whereNotNull('email')
                ->whereHas('memberships', fn ($query) => $query
                    ->where('organization_id', $fresh->organization_id)
                    ->where('role', MembershipRole::Owner))
                ->get();

            if ($owners->isEmpty()) {
                return;
            }

            Notification::send($owners, new OwnerPayoutNotification(
                kind: $kind,
                title: $title,
                message: $message,
                payoutReference: $fresh->reference,
                url: route('owner.settlements.index'),
            ));
        });
    }

    private function sendEarlyRequestToPlatformAdministrators(OwnerPayout $payout): void
    {
        $payoutId = $payout->getKey();

        DB::afterCommit(function () use ($payoutId): void {
            $fresh = OwnerPayout::query()
                ->with(['organization', 'requestedBy'])
                ->find($payoutId);

            if (! $fresh || ! $fresh->organization || ! $fresh->requestedBy) {
                return;
            }

            $administrators = User::query()
                ->where('is_platform_admin', true)
                ->whereNotNull('email')
                ->get();

            if ($administrators->isEmpty()) {
                return;
            }

            Notification::send($administrators, new PlatformOwnerPayoutRequestedNotification(
                organizationName: $fresh->organization->name,
                requesterName: $fresh->requestedBy->name,
                requesterEmail: $fresh->requestedBy->email,
                payoutReference: $fresh->reference,
                currency: $fresh->currency,
                grossAmount: $fresh->gross_amount,
                feeAmount: $fresh->payout_fee,
                netAmount: $fresh->net_amount,
                url: route('platform.owner-payouts.index'),
            ));
        });
    }
}
