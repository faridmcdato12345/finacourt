<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RefundNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly string $kind,
        public readonly string $title,
        public readonly string $message,
        public readonly string $refundReference,
        public readonly string $url,
        public readonly string $actionLabel,
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
            'kind' => $this->kind,
            'title' => $this->title,
            'message' => $this->message,
            'url' => $this->url,
            'refund_reference' => $this->refundReference,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title)
            ->greeting("Hello {$notifiable->name},")
            ->line($this->message)
            ->line("Refund reference: {$this->refundReference}")
            ->action($this->actionLabel, $this->url)
            ->line('FinACourt keeps an auditable record of each refund decision and provider update.');
    }
}
