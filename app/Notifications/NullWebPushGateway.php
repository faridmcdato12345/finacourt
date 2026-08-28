<?php

namespace App\Notifications;

use App\Models\User;
use App\Notifications\Contracts\WebPushGateway;

class NullWebPushGateway implements WebPushGateway
{
    public function send(User $user, array $payload): void
    {
        // A production adapter may send the same payload using VAPID-backed web
        // push. The MVP keeps durable in-app notifications without fake keys.
    }
}
