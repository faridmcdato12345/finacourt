<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OwnerVenuePublishedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly int $venueId,
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
            'kind' => 'owner_venue_published',
            'title' => 'Your venue is now visible to players',
            'message' => "FinACourt completed the final check for {$this->venueName}. Players can now find and book it.",
            'url' => $this->url,
            'venue_id' => $this->venueId,
            'venue_name' => $this->venueName,
            'organization_name' => $this->organizationName,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Your venue is now visible to players: {$this->venueName}")
            ->greeting("Hello {$notifiable->name},")
            ->line("FinACourt completed the final marketplace check for {$this->venueName}.")
            ->line("The venue is now publicly visible under your {$this->organizationName} account, and players can find and book its active courts.")
            ->action('View public venue page', $this->url)
            ->line('Keep the venue published and its court availability up to date so it remains visible.');
    }
}
