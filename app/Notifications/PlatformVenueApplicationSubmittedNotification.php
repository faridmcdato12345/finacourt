<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PlatformVenueApplicationSubmittedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly int $applicationId,
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
            'kind' => 'platform_venue_application_submitted',
            'title' => 'A court owner submitted a new venue application',
            'message' => "{$this->requesterName} submitted {$this->venueName} for ownership review.",
            'url' => $this->url,
            'application_id' => $this->applicationId,
            'venue_name' => $this->venueName,
            'organization_name' => $this->organizationName,
            'requester_name' => $this->requesterName,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("New venue application: {$this->venueName}")
            ->greeting("Hello {$notifiable->name},")
            ->line("{$this->requesterName} ({$this->requesterEmail}) submitted a new venue application for {$this->venueName}.")
            ->line("Owner account: {$this->organizationName}")
            ->line("Venue location: {$this->venueLocation}")
            ->line('The owner workspace remains restricted until FinACourt independently confirms the venue and approves ownership access.')
            ->action('Review venue application', $this->url)
            ->line('Use an independently sourced venue contact or other trustworthy evidence before approving it.');
    }
}
