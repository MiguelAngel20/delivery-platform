<?php

namespace App\Support;

use App\Enums\NotificationPriority;

final class PushMessage
{
    /**
     * @param  array<string, string>  $data
     */
    public function __construct(
        public readonly string $title,
        public readonly string $body,
        public readonly array $data = [],
        public readonly NotificationPriority $priority = NotificationPriority::Normal,
        public readonly ?int $ttlSeconds = null,
    ) {}
}
