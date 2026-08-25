<?php

namespace App\Jobs\Notifications;

use App\Models\User;
use App\Services\Notifications\NotificationIdempotencyService;
use App\Services\Push\PushNotificationService;
use App\Support\PushMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Delivers one FCM push to a user.
 *
 * When $idempotencyKey is set (from RideNotification::dedupeKey), at-most-once
 * delivery uses Cache::add. Critical events also claim a DB row so Redis flush
 * cannot re-send. Distinct keys / null keys never block each other.
 */
final class SendPushToUserJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public int $userId,
        public PushMessage $message,
        public ?string $idempotencyKey = null,
        public bool $persistentIdempotency = false,
    ) {}

    public function handle(
        PushNotificationService $push,
        NotificationIdempotencyService $idempotency,
    ): void {
        $user = User::query()->find($this->userId);

        if ($user === null) {
            return;
        }

        $cacheKey = $this->cacheKey();
        $persistentKey = $this->persistentKey($idempotency);

        if ($persistentKey !== null && ! $idempotency->claim($persistentKey)) {
            return;
        }

        if ($cacheKey !== null && ! Cache::add($cacheKey, true, $this->idempotencyTtlSeconds())) {
            return;
        }

        try {
            $push->sendToUser($user, $this->message);

            if ($persistentKey !== null) {
                $idempotency->markSent($persistentKey);
            }
        } catch (Throwable $exception) {
            if ($cacheKey !== null) {
                Cache::forget($cacheKey);
            }

            if ($persistentKey !== null) {
                $idempotency->markFailed($persistentKey, $exception->getMessage());
            }

            throw $exception;
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::warning('Queued push delivery failed', [
            'user_id' => $this->userId,
            'title' => $this->message->title,
            'idempotency_key' => $this->idempotencyKey,
            'message' => $exception->getMessage(),
        ]);
    }

    private function cacheKey(): ?string
    {
        if ($this->idempotencyKey === null || $this->idempotencyKey === '') {
            return null;
        }

        return 'push:idem:'.$this->userId.':'.$this->idempotencyKey;
    }

    private function persistentKey(NotificationIdempotencyService $idempotency): ?string
    {
        if (! $this->persistentIdempotency) {
            return null;
        }

        if ($this->idempotencyKey === null || $this->idempotencyKey === '') {
            return null;
        }

        return $idempotency->pushKey($this->userId, $this->idempotencyKey);
    }

    private function idempotencyTtlSeconds(): int
    {
        if ($this->persistentIdempotency) {
            return max(
                (int) config('push.dedupe_ttl_seconds', 120),
                (int) config('push.push_idempotency_ttl_seconds', 7 * 24 * 3600),
            );
        }

        return (int) config('push.dedupe_ttl_seconds', 120);
    }
}
