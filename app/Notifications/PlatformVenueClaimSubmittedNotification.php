<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PlatformVenueClaimSubmittedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly int $claimId,
        public readonly string $venueName,
        public readonly string $venueLocation,
        public readonly string $organizationName,
        public readonly string $requesterName,
        public readonly string $requesterEmail,
        public readonly string $relationship,
        public readonly string $url,
    ) {
        $this->onQueue('emails')->afterCommit();
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /** @return array<string, int|string> */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'platform_venue_claim_submitted',
            'title' => 'A court owner submitted a venue ownership request',
            'message' => "{$this->requesterName} submitted {$this->venueName} for independent ownership review.",
            'url' => $this->url,
            'claim_id' => $this->claimId,
            'venue_name' => $this->venueName,
            'organization_name' => $this->organizationName,
            'requester_name' => $this->requesterName,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Venue ownership review requested: {$this->venueName}")
            ->greeting("Hello {$notifiable->name},")
            ->line("{$this->requesterName} ({$this->requesterEmail}) submitted a venue ownership request for {$this->venueName}.")
            ->line("Owner account: {$this->organizationName}")
            ->line("Claimed relationship: {$this->relationship}")
            ->line("Venue location: {$this->venueLocation}")
            ->line('The request is waiting for an independent venue ownership check. Claimant-supplied contact details are not proof by themselves.')
            ->action('Review venue ownership request', $this->url)
            ->line('Record an independently sourced venue check before approving access.');
    }
}
