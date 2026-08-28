<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VenueClaimVerificationCode extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $venueName,
        public readonly string $code,
        public readonly string $expiresAt,
        public readonly string $listingUrl,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Ownership review code for {$this->venueName}")
            ->greeting('Venue ownership review')
            ->line("Someone asked to manage {$this->venueName} on FinACourt.")
            ->line("Verification code: {$this->code}")
            ->line("This code expires {$this->expiresAt}.")
            ->line('Share this code only if you authorized the request. FinACourt will still perform a separate marketplace review before the venue can accept bookings.')
            ->line('If you did not authorize this request, do not share the code. Open the venue page and use “Something not right?” to report it.')
            ->action('Review the venue page', $this->listingUrl);
    }
}
