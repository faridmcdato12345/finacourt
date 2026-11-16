<?php

namespace App\Notifications;

use App\Models\ReactivationCampaignRecipient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReactivationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(private readonly ReactivationCampaignRecipient $recipient)
    {
        $this->onQueue('emails')->afterCommit();
    }

    public function recipientId(): int
    {
        return $this->recipient->getKey();
    }

    /**
     * Laravel invokes this after the queued notification has exhausted its
     * retry policy. A single successful channel is recorded separately by the
     * NotificationSent listener, so the failure handler remains idempotent.
     */
    public function failed(\Throwable $exception): void
    {
        Log::warning('Customer reactivation notification failed after retries.', [
            'recipient_id' => $this->recipientId(),
            'exception' => $exception::class,
        ]);

        app(\App\Listeners\RecordReactivationDeliveryFailure::class)->handle($this->recipientId());
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        $preference = $notifiable->marketingPreference;
        $channels = [];

        if ($preference?->canReceiveInAppMarketing()) {
            $channels[] = 'database';
        }

        if ($preference?->canReceiveEmailMarketing() && filled($notifiable->routeNotificationFor('mail'))) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /** @return array<string, string> */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'customer_reactivation',
            'title' => $this->recipient->campaign->title,
            'message' => $this->messageFor($notifiable),
            'url' => route('player.reactivation.click', $this->recipient->click_token),
            'campaign_token' => $this->recipient->campaign->campaign_token,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $campaign = $this->recipient->campaign;
        $venue = $campaign->venue;
        $offer = $this->recipient->customerOffer;
        $url = route('player.reactivation.click', $this->recipient->click_token);
        $viewData = [
            'playerName' => $notifiable->name,
            'campaignTitle' => $campaign->title,
            'campaignMessage' => $this->messageFor($notifiable),
            'venueName' => $venue->name,
            'ctaUrl' => $url,
            'preferencesUrl' => route('player.preferences.edit'),
            'logoUrl' => asset('icons/app-logo.png'),
            'suggestedCourt' => $this->recipient->suggestedResource?->name,
            'suggestedDate' => $this->recipient->suggested_date?->format('D, M j'),
            'suggestedTime' => $this->formatSuggestedTime(),
            'offerLabel' => $offer?->campaign?->label(),
            'offerExpiration' => $offer?->expires_at?->setTimezone($venue->organization->timezone)->format('M j, Y'),
            'offerMinimumSpend' => $offer?->minimum_spend,
        ];

        return (new MailMessage)
            ->subject("{$campaign->title} at {$venue->name} 🎉")
            ->action('Find my next game', $url)
            ->view([
                'html' => 'mail.reactivation',
                'text' => 'mail.reactivation-text',
            ], $viewData);
    }

    private function formatSuggestedTime(): ?string
    {
        if (blank($this->recipient->suggested_start_time)) {
            return null;
        }

        $time = \DateTimeImmutable::createFromFormat(
            'H:i',
            substr((string) $this->recipient->suggested_start_time, 0, 5),
        );

        return $time?->format('g:i A');
    }

    private function messageFor(object $notifiable): string
    {
        $offer = $this->recipient->customerOffer;
        $venue = $this->recipient->campaign->venue;
        $replacements = [
            '{{customer_name}}' => (string) $notifiable->name,
            '{{venue_name}}' => $venue->name,
            '{{offer_amount}}' => $offer?->campaign?->label() ?? '',
            '{{offer_expiration}}' => $offer?->expires_at?->setTimezone($venue->organization->timezone)->format('M j, Y') ?? '',
            '{{booking_link}}' => route('player.reactivation.click', $this->recipient->click_token),
        ];

        return strtr($this->recipient->campaign->message, $replacements);
    }
}
