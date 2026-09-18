<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CourtClosureNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly string $kind,
        public readonly string $title,
        public readonly string $message,
        public readonly string $closureReference,
        public readonly string $url,
        public readonly string $actionLabel,
    ) {
        $this->onQueue('emails')->afterCommit();
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return $notifiable instanceof AnonymousNotifiable
            ? ['mail']
            : ['database', 'mail'];
    }

    /** @return array<string, string> */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => $this->kind,
            'title' => $this->title,
            'message' => $this->message,
            'url' => $this->url,
            'closure_reference' => $this->closureReference,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->title)
            ->greeting('Important FinACourt booking update')
            ->line($this->message)
            ->line("Closure reference: {$this->closureReference}");

        if ($this->url !== '') {
            $mail->action($this->actionLabel, $this->url);
        }

        return $mail->line('Keep this email for your records.');
    }
}
