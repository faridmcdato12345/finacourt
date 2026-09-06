<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PlatformClaimedVenueReviewRequestedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly int $venueId,
        public readonly string $venueName,
        public readonly string $venueLocation,
        public readonly string $organizationName,
        public readonly string $requesterName,
        public readonly string $requesterEmail,
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
            'kind' => 'platform_claimed_venue_review_requested',
            'title' => 'A claimed venue is ready for its final check',
            'message' => "{$this->requesterName} asked FinACourt to make {$this->venueName} visible to players.",
            'url' => $this->url,
            'venue_id' => $this->venueId,
            'venue_name' => $this->venueName,
            'organization_name' => $this->organizationName,
            'requester_name' => $this->requesterName,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Final venue review requested: {$this->venueName}")
            ->greeting("Hello {$notifiable->name},")
            ->line("{$this->requesterName} ({$this->requesterEmail}) saved {$this->venueName} as ready to show to players.")
            ->line("Owner account: {$this->organizationName}")
            ->line("Venue location: {$this->venueLocation}")
            ->line('The claimed venue remains private until a platform administrator completes the final marketplace check.')
            ->action('Complete final venue check', $this->url)
            ->line('Review its public details, active courts, prices, and ownership audit before approval.');
    }
}
