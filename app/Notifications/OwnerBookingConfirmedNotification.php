<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OwnerBookingConfirmedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly string $bookingReference,
        public readonly string $organizationName,
        public readonly string $venueName,
        public readonly string $courtName,
        public readonly string $playerName,
        public readonly ?string $playerEmail,
        public readonly ?string $playerPhone,
        public readonly string $date,
        public readonly string $time,
        public readonly string $timezone,
        public readonly string $paymentLabel,
        public readonly string $bookingValue,
        public readonly string $ownerBookingUrl,
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
        $mail = (new MailMessage)
            ->subject("New confirmed booking at {$this->venueName}")
            ->greeting("Hello {$notifiable->name},")
            ->line("A player confirmed a court booking for {$this->organizationName}.")
            ->line("Booking reference: {$this->bookingReference}")
            ->line("Venue and court: {$this->venueName} · {$this->courtName}")
            ->line("Date and time: {$this->date}, {$this->time} ({$this->timezone})")
            ->line("Player: {$this->playerName}");

        if ($this->playerEmail) {
            $mail->line("Player email: {$this->playerEmail}");
        }

        if ($this->playerPhone) {
            $mail->line("Player phone: {$this->playerPhone}");
        }

        return $mail
            ->line("Payment: {$this->paymentLabel}")
            ->line("Court booking value: {$this->bookingValue}")
            ->action('View the booking', $this->ownerBookingUrl)
            ->line('This is an operational booking notice from FinACourt.');
    }
}
