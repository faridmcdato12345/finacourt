<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StaffInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly string $organizationName,
        public readonly string $inviterName,
        public readonly string $url,
        public readonly string $expiresAt,
    ) {
        $this->onQueue('emails')->afterCommit();
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Join {$this->organizationName} on FinACourt")
            ->greeting('You have been invited to FinACourt')
            ->line("{$this->inviterName} invited you to join {$this->organizationName} as a staff member.")
            ->line('Your access is limited to the permissions selected by the court owner. Financial payouts and team administration remain owner-only.')
            ->action('Review staff invitation', $this->url)
            ->line("This secure invitation expires {$this->expiresAt} and can be used only once.")
            ->line('If you were not expecting this invitation, you can safely ignore this email.');
    }
}
