<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PlatformOwnerPayoutRequestedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly string $organizationName,
        public readonly string $requesterName,
        public readonly string $requesterEmail,
        public readonly string $payoutReference,
        public readonly string $currency,
        public readonly string $grossAmount,
        public readonly string $feeAmount,
        public readonly string $netAmount,
        public readonly string $url,
    ) {
        $this->onQueue('emails')->afterCommit();
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /** @return array<string, string> */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'platform_owner_payout_requested',
            'title' => 'A court owner requested an early payout',
            'message' => "{$this->requesterName} requested {$this->currency} {$this->netAmount} for {$this->organizationName}.",
            'url' => $this->url,
            'payout_reference' => $this->payoutReference,
            'organization_name' => $this->organizationName,
            'requester_name' => $this->requesterName,
            'gross_amount' => $this->grossAmount,
            'fee_amount' => $this->feeAmount,
            'net_amount' => $this->netAmount,
            'currency' => $this->currency,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Early payout requested by {$this->organizationName}")
            ->greeting("Hello {$notifiable->name},")
            ->line("{$this->requesterName} ({$this->requesterEmail}) requested an early payout for {$this->organizationName}.")
            ->line("Gross owner earnings: {$this->currency} {$this->grossAmount}")
            ->line("Transfer fee: {$this->currency} {$this->feeAmount}")
            ->line("Net amount to transfer: {$this->currency} {$this->netAmount}")
            ->line("Payout reference: {$this->payoutReference}")
            ->action('Review payout request', $this->url)
            ->line('Verify the payout destination and complete the transfer before marking the payout paid.');
    }
}
