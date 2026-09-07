<?php

namespace App\Notifications;

use App\Models\ReactivationCampaignRecipient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
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
            'message' => $this->recipient->campaign->message,
            'url' => route('player.reactivation.click', $this->recipient->click_token),
            'campaign_token' => $this->recipient->campaign->campaign_token,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $campaign = $this->recipient->campaign;
        $venue = $campaign->venue;
        $url = route('player.reactivation.click', $this->recipient->click_token);
        $viewData = [
            'playerName' => $notifiable->name,
            'campaignTitle' => $campaign->title,
            'campaignMessage' => $campaign->message,
            'venueName' => $venue->name,
            'ctaUrl' => $url,
            'preferencesUrl' => route('player.preferences.edit'),
            'logoUrl' => asset('icons/finacourt-logo-192.png'),
            'suggestedCourt' => $this->recipient->suggestedResource?->name,
            'suggestedDate' => $this->recipient->suggested_date?->format('D, M j'),
            'suggestedTime' => $this->formatSuggestedTime(),
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
}
