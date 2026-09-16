<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PlayerMagicLoginNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly string $url,
        public readonly int $expiresInMinutes,
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
            ->subject('Your secure FinACourt sign-in link')
            ->greeting("Hello {$notifiable->name},")
            ->line('Use this secure link to continue your reservation or manage your FinACourt bookings without a password.')
            ->action('Continue to FinACourt', $this->url)
            ->line("This one-time link expires in {$this->expiresInMinutes} minutes.")
            ->line('If you did not request this link, you can safely ignore this email.');
    }
}
