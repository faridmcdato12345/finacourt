<?php

namespace App\Notifications;

use App\Models\ReactivationCampaignRecipient;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ReactivationNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly ReactivationCampaignRecipient $recipient) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
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
}
