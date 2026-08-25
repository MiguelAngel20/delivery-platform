<?php

namespace App\Listeners\Notifications;

use App\Models\User;
use App\Notifications\Auth\CustomerEmailVerificationCode;
use App\Notifications\RideNotification;
use App\Services\Notifications\NotificationIdempotencyService;
use Illuminate\Notifications\Events\NotificationSent;

/**
 * Mark persistent claims as sent once a channel delivers successfully.
 */
final class HandleRideNotificationSent
{
    public function __construct(
        private readonly NotificationIdempotencyService $idempotency,
    ) {}

    public function handle(NotificationSent $event): void
    {
        $notification = $event->notification;
        $notifiable = $event->notifiable;

        if (! $notifiable instanceof User) {
            return;
        }

        if ($notification instanceof CustomerEmailVerificationCode) {
            $this->idempotency->markSent(
                $this->idempotency->otpMailKey($notification->issuanceId),
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

        $this->idempotency->markSent(
            $this->idempotency->notificationKey($notifiable->id, $dedupeKey),
        );
    }
}
