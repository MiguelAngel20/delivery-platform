<?php

namespace App\Jobs\Notifications;

use App\Models\DriverRating;
use App\Models\Order;
use App\Notifications\Orders\DriverRatingPromptNotification;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

/**
 * Delayed customer prompt to rate the driver after delivery.
 *
 * Uniqueness: one queued job per order (ShouldBeUnique).
 * Idempotency: Cache lock + skip if already rated or a database notification
 * with the same dedupe_key already exists (survives retries after partial success).
 */
final class SendDriverRatingPromptJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(public int $orderId) {}

    public function uniqueId(): string
    {
        return (string) $this->orderId;
    }

    /**
     * Keep the unique lock at least through the configured delay window.
     */
    public function uniqueFor(): int
    {
        $delaySeconds = (int) config('push.rating_prompt_delay_minutes', 1440) * 60;

        return max(7 * 24 * 3600, $delaySeconds + 3600);
    }

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

        Cache::lock('job:rating-prompt:'.$order->id, 15)->block(10, function () use ($order): void {
            $alreadyRated = DriverRating::query()
                ->where('order_id', $order->id)
                ->where('driver_id', $order->assigned_driver_id)
                ->exists();

            if ($alreadyRated) {
                return;
            }

            $dedupeKey = 'rating-prompt:'.$order->id;
            $user = $order->customer->user;

            $alreadyPrompted = $user->notifications()
                ->where('type', DriverRatingPromptNotification::class)
                ->where('data->dedupe_key', $dedupeKey)
                ->exists();

            if ($alreadyPrompted) {
                return;
            }

            $user->notify(new DriverRatingPromptNotification($order));
        });
    }
}
