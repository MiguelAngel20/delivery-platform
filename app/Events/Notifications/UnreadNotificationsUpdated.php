<?php

namespace App\Events\Notifications;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class UnreadNotificationsUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $userId,
        public int $unreadCount,
        public ?string $title = null,
        public ?string $body = null,
        public ?string $notificationId = null,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.'.$this->userId.'.notifications'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'UnreadNotificationsUpdated';
    }

    /**
     * @return array{unread_count: int, title: ?string, body: ?string, notification_id: ?string}
     */
    public function broadcastWith(): array
    {
        return [
            'unread_count' => $this->unreadCount,
            'title' => $this->title,
            'body' => $this->body,
            'notification_id' => $this->notificationId,
        ];
    }
}
