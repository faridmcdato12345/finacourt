<?php

namespace App\Mail;

use App\Enums\OutreachMessageType;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

class OutreachMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $venueName,
        public readonly string $privateLink,
        public readonly OutreachMessageType $messageType = OutreachMessageType::Initial,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(
                (string) config('outreach.from.address'),
                (string) config('outreach.from.name'),
            ),
            replyTo: [new Address((string) config('outreach.reply_to'), 'Farid - FinACourt')],
            subject: $this->messageType->subject($this->venueName),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.outreach',
            text: 'mail.outreach-text',
            with: [
                'venueName' => $this->venueName,
                'privateLink' => $this->privateLink,
                'messageType' => $this->messageType,
                'ownerOverviewUrl' => (string) config('outreach.owner_overview_url'),
                'logoPath' => public_path('icons/finacourt-logo-192.png'),
                'replyToAddress' => (string) config('outreach.reply_to'),
            ],
        );
    }

    public function headers(): Headers
    {
        $replyTo = (string) config('outreach.reply_to');

        return new Headers(text: [
            'List-Unsubscribe' => "<mailto:{$replyTo}?subject=Unsubscribe>",
        ]);
    }
}
