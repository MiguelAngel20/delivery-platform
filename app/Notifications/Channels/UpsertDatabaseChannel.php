<?php

namespace App\Notifications\Channels;

use App\Notifications\RideNotification;
use Illuminate\Notifications\Channels\DatabaseChannel;
use Illuminate\Notifications\Notification;

/**
 * Stores a normal inbox row, or updates the existing row when the
 * notification belongs to a customer order thread.
 */
class UpsertDatabaseChannel extends DatabaseChannel
{
    /**
     * @param  mixed  $notifiable
     */
    public function send($notifiable, Notification $notification)
    {
        $threadKey = $notification instanceof RideNotification
            ? $notification->threadKey()
            : null;

        if (! is_string($threadKey) || $threadKey === '') {
            return parent::send($notifiable, $notification);
        }

        $payload = $this->buildPayload($notifiable, $notification);
        $existing = $notifiable->notifications()
            ->where('data->thread_key', $threadKey)
            ->latest('created_at')
            ->first();

        if ($existing === null) {
            return $notifiable->routeNotificationFor('database', $notification)->create($payload);
        }

        $existing->forceFill([
            'data' => $payload['data'],
            'read_at' => null,
            'created_at' => now(),
        ])->save();

        return $existing;
    }
}
