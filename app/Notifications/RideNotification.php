<?php

namespace App\Notifications;

use App\Enums\NotificationCategory;
use App\Enums\NotificationPriority;
use App\Models\User;
use App\Notifications\Channels\FcmChannel;
use App\Notifications\Concerns\RideNotificationContract;
use App\Services\Notifications\NotificationIdempotencyService;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Cache;

abstract class RideNotification extends Notification implements RideNotificationContract
{
    abstract public function title(): string;

    abstract public function body(): string;

    public function via(object $notifiable): array
    {
        if (! $notifiable instanceof User) {
            return [];
        }

        $dedupeKey = $this->dedupeKey();

        if ($dedupeKey !== null) {
            $cacheTtl = $this->requiresPersistentDedupe()
                ? max(
                    (int) config('push.dedupe_ttl_seconds', 120),
                    (int) config('push.push_idempotency_ttl_seconds', 7 * 24 * 3600),
                )
                : (int) config('push.dedupe_ttl_seconds', 120);

            $cacheKey = sprintf('notif:dedupe:%d:%s', $notifiable->id, $dedupeKey);

            if (! Cache::add($cacheKey, true, $cacheTtl)) {
                return [];
            }

            if ($this->requiresPersistentDedupe()) {
                $idempotency = app(NotificationIdempotencyService::class);
                $persistentKey = $idempotency->notificationKey($notifiable->id, $dedupeKey);

                if (! $idempotency->claim($persistentKey)) {
                    return [];
                }
            }
        }

        return ['database', FcmChannel::class];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'category' => $this->category()->value,
            'type' => class_basename(static::class),
            'title' => $this->title(),
            'body' => $this->body(),
            'target_type' => $this->targetType(),
            'target_id' => $this->targetId(),
            'click_path' => $this->clickPath(),
            'priority' => $this->priority()->value,
            'dedupe_key' => $this->dedupeKey(),
        ];
    }

    public function priority(): NotificationPriority
    {
        return NotificationPriority::Normal;
    }

    public function ttlSeconds(): ?int
    {
        return (int) config('push.ttl.order_update_seconds', 3600);
    }

    public function isCritical(): bool
    {
        return false;
    }

    public function requiresPersistentDedupe(): bool
    {
        return false;
    }

    public function dedupeKey(): ?string
    {
        return null;
    }

    public function targetType(): ?string
    {
        return null;
    }

    public function targetId(): ?int
    {
        return null;
    }

    /**
     * @return array<string, string>
     */
    public function pushData(): array
    {
        $data = [
            'category' => $this->category()->value,
            'type' => class_basename(static::class),
        ];

        if ($this->targetType() !== null) {
            $data['target_type'] = $this->targetType();
        }

        if ($this->targetId() !== null) {
            $data['target_id'] = (string) $this->targetId();
        }

        $path = $this->clickPath();

        if ($path !== null) {
            $data['click_path'] = $path;
        }

        return $data;
    }

    public function clickPath(): ?string
    {
        return null;
    }

    public function category(): NotificationCategory
    {
        return NotificationCategory::System;
    }
}
