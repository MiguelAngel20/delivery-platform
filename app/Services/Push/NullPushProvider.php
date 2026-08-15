<?php

namespace App\Services\Push;

use App\Contracts\PushProvider;
use App\Support\PushMessage;
use Illuminate\Support\Facades\Log;

final class NullPushProvider implements PushProvider
{
    public function send(string $token, PushMessage $message): array
    {
        Log::debug('Push skipped (null provider)', [
            'token_suffix' => substr($token, -8),
            'title' => $message->title,
        ]);

        return ['sent' => 0, 'failed' => 0, 'invalidated' => []];
    }

    public function sendToMany(array $tokens, PushMessage $message): array
    {
        return ['sent' => 0, 'failed' => 0, 'invalidated' => []];
    }
}
