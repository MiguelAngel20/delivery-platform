<?php

namespace App\Notifications\Channels;

use App\Models\User;
use App\Notifications\Concerns\RideNotificationContract;
use App\Services\Notifications\NotificationPreferenceService;
use App\Services\Push\PushNotificationService;
use App\Support\PushMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Throwable;

final class FcmChannel
{
    public function __construct(
        private readonly PushNotificationService $push,
        private readonly NotificationPreferenceService $preferences,
    ) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (! $notifiable instanceof User) {
            return;
        }

        if (! $notification instanceof RideNotificationContract) {
            return;
        }

        if (! $this->preferences->allowsPush(
            $notifiable,
            $notification->category(),
            $notification->isCritical(),
        )) {
            return;
        }

        $message = new PushMessage(
            title: $notification->title(),
            body: $notification->body(),
            data: $notification->pushData(),
            priority: $notification->priority(),
            ttlSeconds: $notification->ttlSeconds(),
        );

        try {
            $this->push->sendToUser($notifiable, $message);
        } catch (Throwable $exception) {
            Log::warning('Push delivery failed', [
                'user_id' => $notifiable->id,
                'notification' => class_basename($notification),
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}
