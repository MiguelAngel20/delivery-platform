<?php

namespace App\Jobs\Notifications;

use App\Models\Order;
use App\Notifications\Orders\DriverRatingPromptNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class SendDriverRatingPromptJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(public int $orderId) {}

    public function handle(): void
    {
        $order = Order::query()
            ->with('customer.user')
            ->find($this->orderId);

        if ($order === null || $order->customer?->user === null) {
            return;
        }

        if ($order->assigned_driver_id === null) {
            return;
        }

        $order->customer->user->notify(new DriverRatingPromptNotification($order));
    }
}
