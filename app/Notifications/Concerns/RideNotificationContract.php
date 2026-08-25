<?php

namespace App\Notifications\Concerns;

use App\Enums\NotificationCategory;
use App\Enums\NotificationPriority;

interface RideNotificationContract
{
    public function category(): NotificationCategory;

    public function title(): string;

    public function body(): string;

    /**
     * @return array<string, string>
     */
    public function pushData(): array;

    public function priority(): NotificationPriority;

    public function ttlSeconds(): ?int;

    public function isCritical(): bool;

    /**
     * When true, duplicates must be blocked via persistent DB claims (not only cache TTL).
     */
    public function requiresPersistentDedupe(): bool;

    public function dedupeKey(): ?string;
}
