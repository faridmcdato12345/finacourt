<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OwnerVenueClaimApprovedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly int $claimId,
        public readonly string $venueName,
        public readonly string $organizationName,
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
            'kind' => 'owner_venue_claim_approved',
            'title' => 'Your venue ownership request was approved',
            'message' => "{$this->venueName} is now connected privately to your {$this->organizationName} workspace.",
            'url' => $this->url,
            'claim_id' => $this->claimId,
            'venue_name' => $this->venueName,
            'organization_name' => $this->organizationName,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Venue ownership approved: {$this->venueName}")
            ->greeting("Hello {$notifiable->name},")
            ->line("FinACourt approved your ownership request for {$this->venueName}.")
            ->line("The venue is now connected privately to your {$this->organizationName} workspace, and your owner tools are available.")
            ->line('Finish the venue details and court setup before asking FinACourt to make it bookable for players.')
            ->action('Finish venue setup', $this->url)
            ->line('Venue ownership approval does not publish the venue automatically.');
    }
}
