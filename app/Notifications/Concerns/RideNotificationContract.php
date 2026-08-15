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

    public function dedupeKey(): ?string;
}
