<?php

namespace App\Contracts;

use App\Support\PushMessage;

interface PushProvider
{
    /**
     * @return array{sent: int, failed: int, invalidated: list<string>}
     */
    public function send(string $token, PushMessage $message): array;

    /**
     * @param  list<string>  $tokens
     * @return array{sent: int, failed: int, invalidated: list<string>}
     */
    public function sendToMany(array $tokens, PushMessage $message): array;
}
