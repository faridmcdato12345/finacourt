<?php

namespace App\Notifications\Contracts;

use App\Models\User;

interface WebPushGateway
{
    /** @param array<string, string> $payload */
    public function send(User $user, array $payload): void;
}
