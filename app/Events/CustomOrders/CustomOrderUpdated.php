<?php

namespace App\Events\CustomOrders;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class CustomOrderUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<string>  $channels
     */
    public function __construct(
        public array $payload,
        public array $channels,
        public string $eventName = 'CustomOrderUpdated',
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return array_map(
            static fn (string $channel): PrivateChannel => new PrivateChannel($channel),
            $this->channels,
        );
    }

    public function broadcastAs(): string
    {
        return $this->eventName;
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
