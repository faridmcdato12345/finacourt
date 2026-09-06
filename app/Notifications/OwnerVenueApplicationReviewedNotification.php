<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OwnerVenueApplicationReviewedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly int $applicationId,
        public readonly string $venueName,
        public readonly string $organizationName,
        public readonly bool $approved,
        public readonly string $reviewNotes,
        public readonly string $url,
    ) {
        $this->onQueue('emails')->afterCommit();
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /** @return array<string, int|string|bool> */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => $this->approved ? 'owner_venue_application_approved' : 'owner_venue_application_rejected',
            'title' => $this->approved ? 'Your venue application was approved' : 'Your venue application needs changes',
            'message' => $this->approved
                ? "{$this->venueName} is approved for private setup."
                : "FinACourt could not approve {$this->venueName} yet.",
            'url' => $this->url,
            'application_id' => $this->applicationId,
            'venue_name' => $this->venueName,
            'organization_name' => $this->organizationName,
            'approved' => $this->approved,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject($this->approved
                ? "Venue ownership approved: {$this->venueName}"
                : "Venue application needs changes: {$this->venueName}")
            ->greeting("Hello {$notifiable->name},");

        if ($this->approved) {
            return $message
                ->line("FinACourt approved your new venue application for {$this->venueName}.")
                ->line("Your {$this->organizationName} workspace is unlocked so you can add courts, prices, hours, and photos.")
                ->line("Review note: {$this->reviewNotes}")
                ->action('Continue venue setup', $this->url)
                ->line('This approval is private. You must request a final marketplace review before players can find or book the venue.');
        }

        return $message
            ->line("FinACourt could not approve your new venue application for {$this->venueName} yet.")
            ->line("Review note: {$this->reviewNotes}")
            ->action('Update venue application', $this->url)
            ->line('Correct the venue details and save again to resubmit the application.');
    }
}
