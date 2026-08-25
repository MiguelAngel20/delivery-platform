<?php

namespace App\Notifications\Channels;

use App\Jobs\Notifications\SendPushToUserJob;
use App\Models\User;
use App\Notifications\Concerns\RideNotificationContract;
use App\Services\Notifications\NotificationPreferenceService;
use App\Support\PushMessage;
use Illuminate\Notifications\Notification;

final class FcmChannel
{
    public function __construct(
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

        SendPushToUserJob::dispatchAfterResponse(
            $notifiable->id,
            new PushMessage(
                title: $notification->title(),
                body: $notification->body(),
                data: $notification->pushData(),
                priority: $notification->priority(),
                ttlSeconds: $notification->ttlSeconds(),
            ),
            $notification->dedupeKey(),
            $notification->requiresPersistentDedupe(),
        );
    }
}
