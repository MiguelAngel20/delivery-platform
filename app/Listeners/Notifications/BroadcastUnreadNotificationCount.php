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

        $latest = $notifiable->unreadNotifications()->latest()->first();
        $data = is_array($latest?->data) ? $latest->data : [];

        SafeBroadcast::event(new UnreadNotificationsUpdated(
            $notifiable->id,
            $notifiable->unreadNotifications()->count(),
            isset($data['title']) && is_string($data['title']) ? $data['title'] : null,
            isset($data['body']) && is_string($data['body']) ? $data['body'] : null,
            $latest?->id,
        ));
    }
}
