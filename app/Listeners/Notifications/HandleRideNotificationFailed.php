<?php

namespace App\Listeners\Notifications;

use App\Models\User;
use App\Notifications\Auth\CustomerEmailVerificationCode;
use App\Notifications\RideNotification;
use App\Services\Notifications\NotificationIdempotencyService;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Support\Facades\Cache;

/**
 * On real delivery failure before a durable side effect, reopen the claim for retry
 * without deleting the row (status=failed keeps audit trail).
 */
final class HandleRideNotificationFailed
{
    public function __construct(
        private readonly NotificationIdempotencyService $idempotency,
    ) {}

    public function handle(NotificationFailed $event): void
    {
        $notification = $event->notification;
        $notifiable = $event->notifiable;

        if (! $notifiable instanceof User) {
            return;
        }

        if ($notification instanceof CustomerEmailVerificationCode) {
            $this->idempotency->markFailed(
                $this->idempotency->otpMailKey($notification->issuanceId),
                $this->failureMessage($event),
            );

            return;
        }

        if (! $notification instanceof RideNotification) {
            return;
        }

        if (! $notification->requiresPersistentDedupe()) {
            return;
        }

        $dedupeKey = $notification->dedupeKey();

        if ($dedupeKey === null) {
            return;
        }

        $persistentKey = $this->idempotency->notificationKey($notifiable->id, $dedupeKey);

        $alreadyPersisted = $notifiable->notifications()
            ->where('type', $notification::class)
            ->where('data->dedupe_key', $dedupeKey)
            ->exists();

        if ($alreadyPersisted) {
            // Database side effect already landed — keep at-most-once.
            $this->idempotency->markSent($persistentKey);

            return;
        }

        $this->idempotency->markFailed($persistentKey, $this->failureMessage($event));

        Cache::forget(sprintf('notif:dedupe:%d:%s', $notifiable->id, $dedupeKey));
    }

    private function failureMessage(NotificationFailed $event): string
    {
        $exception = $event->data['exception'] ?? null;

        if ($exception instanceof \Throwable) {
            return $exception->getMessage();
        }

        if (is_string($exception)) {
            return $exception;
        }

        return 'notification channel failed: '.$event->channel;
    }
}
