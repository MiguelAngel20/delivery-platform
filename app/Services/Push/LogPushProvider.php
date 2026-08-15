<?php

namespace App\Services\Push;

use App\Contracts\PushProvider;
use App\Support\PushMessage;
use Illuminate\Support\Facades\Log;

final class LogPushProvider implements PushProvider
{
    public function send(string $token, PushMessage $message): array
    {
        Log::info('Push log driver', [
            'token_suffix' => substr($token, -8),
            'title' => $message->title,
            'body' => $message->body,
            'data' => $message->data,
            'priority' => $message->priority->value,
        ]);

        return ['sent' => 1, 'failed' => 0, 'invalidated' => []];
    }

    public function sendToMany(array $tokens, PushMessage $message): array
    {
        $sent = 0;

        foreach ($tokens as $token) {
            $this->send($token, $message);
            $sent++;
        }

        return ['sent' => $sent, 'failed' => 0, 'invalidated' => []];
    }
}
