<?php

namespace App\Services\Realtime;

use App\Events\CustomOrders\CustomOrderUpdated;
use App\Models\CustomOrderRequest;
use App\Services\Notifications\RideNotificationDispatcher;
use App\Support\CustomOrderBroadcastPayload;
use App\Support\SafeBroadcast;
use Illuminate\Support\Facades\DB;

final class CustomOrderRealtimePublisher
{
    public function __construct(
        private readonly RideNotificationDispatcher $notifications,
    ) {}

    public function requested(CustomOrderRequest $request): void
    {
        $this->broadcast($request, 'CustomOrderRequested', [
            'customer.'.$request->customer_id,
            'admin',
        ]);
        $this->notifications->customOrderRequested($request);
    }

    public function quoteCreated(CustomOrderRequest $request): void
    {
        $this->broadcast($request, 'CustomOrderQuoteCreated', [
            'customer.'.$request->customer_id,
            'admin',
        ]);
        $this->notifications->customQuoteReady($request);
    }

    public function quoteAccepted(CustomOrderRequest $request): void
    {
        $this->broadcast($request, 'CustomOrderQuoteAccepted', [
            'customer.'.$request->customer_id,
            'admin',
        ]);
    }

    public function converted(CustomOrderRequest $request): void
    {
        $this->broadcast($request, 'CustomOrderConverted', [
            'customer.'.$request->customer_id,
            'admin',
        ]);
    }

    /**
     * @param  list<string>  $channels
     */
    private function broadcast(CustomOrderRequest $request, string $eventName, array $channels): void
    {
        $payload = CustomOrderBroadcastPayload::base($request);

        $callback = static function () use ($payload, $channels, $eventName): void {
            SafeBroadcast::event(new CustomOrderUpdated($payload, $channels, $eventName));
        };

        if (DB::transactionLevel() > 0) {
            DB::afterCommit($callback);

            return;
        }

        $callback();
    }
}
