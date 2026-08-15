<?php

namespace App\Listeners\Notifications;

use App\Events\Notifications\UnreadNotificationsUpdated;
use App\Models\User;
use App\Support\SafeBroadcast;
use Illuminate\Notifications\Events\NotificationSent;

final class BroadcastUnreadNotificationCount
{
    public function handle(NotificationSent $event): void
    {
        if ($event->channel !== 'database') {
            return;
        }

        $notifiable = $event->notifiable;

        if (! $notifiable instanceof User) {
            return;
        }

        SafeBroadcast::event(new UnreadNotificationsUpdated(
            $notifiable->id,
            $notifiable->unreadNotifications()->count(),
        ));
    }
}
