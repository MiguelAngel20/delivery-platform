<?php

namespace App\Services\Realtime;

use App\Enums\DriverApprovalStatus;
use App\Enums\DriverAvailabilityStatus;
use App\Enums\OrderStatus;
use App\Events\Orders\DriverAssigned;
use App\Events\Orders\OrderAvailableToDriver;
use App\Events\Orders\OrderCreated;
use App\Events\Orders\OrderStatusChanged;
use App\Models\Driver;
use App\Models\Order;
use App\Services\Dispatch\DriverEligibilityService;
use App\Services\Notifications\RideNotificationDispatcher;
use App\Support\OrderBroadcastPayload;
use App\Support\SafeBroadcast;
use Illuminate\Support\Facades\DB;

final class OrderRealtimePublisher
{
    public function __construct(
        private readonly DriverEligibilityService $eligibility,
        private readonly RideNotificationDispatcher $notifications,
    ) {}

    public function orderCreated(Order $order): void
    {
        $order->loadMissing(['branch.business']);
        $payload = OrderBroadcastPayload::base($order);
        $channels = $this->audienceForCreated($order);

        $this->afterCommit(function () use ($payload, $channels, $order): void {
            if ($channels !== []) {
                SafeBroadcast::event(new OrderCreated($payload, $channels));
            }

            $this->notifications->orderCreated($order);
        });
    }

    public function statusChanged(Order $order, OrderStatus $previous): void
    {
        $order->loadMissing(['branch.business', 'assignedDriver.user']);
        $payload = OrderBroadcastPayload::withPrevious($order, $previous);
        $channels = $this->audienceForStatus($order);

        $this->afterCommit(function () use ($payload, $channels, $order, $previous): void {
            if ($channels !== []) {
                SafeBroadcast::event(new OrderStatusChanged($payload, $channels));
            }

            $this->notifications->statusChanged($order, $previous);

            if ($order->assigned_driver_id !== null) {
                return;
            }

            $status = $order->order_status;

            if ($status === OrderStatus::Preparing
                || ($status === OrderStatus::SearchingDriver && $previous !== OrderStatus::Preparing)
            ) {
                $this->notifyEligibleDrivers($order, $payload, 'offer');
            }

            if ($status === OrderStatus::ReadyForPickup) {
                $this->notifyEligibleDrivers($order, $payload, 'ready');
            }
        });
    }

    public function driverAssigned(Order $order, OrderStatus $previous): void
    {
        $order->loadMissing(['branch.business', 'assignedDriver.user']);
        $payload = OrderBroadcastPayload::withPrevious($order, $previous);
        $channels = $this->audienceForStatus($order);

        if ($order->assigned_driver_id !== null) {
            $channels[] = 'driver.'.$order->assigned_driver_id;

            if ($order->branch_id !== null) {
                $channels[] = 'branch.'.$order->branch_id.'.offers';
            }
        }

        $channels = array_values(array_unique($channels));

        $this->afterCommit(function () use ($payload, $channels, $order): void {
            if ($channels !== []) {
                SafeBroadcast::event(new DriverAssigned($payload, $channels));
                SafeBroadcast::event(new OrderStatusChanged($payload, $channels));
            }

            $this->notifications->driverAssigned($order);
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  'offer'|'ready'  $kind
     */
    private function notifyEligibleDrivers(Order $order, array $payload, string $kind): void
    {
        $drivers = Driver::query()
            ->with(['user', 'businesses', 'branches'])
            ->where('approval_status', DriverApprovalStatus::Approved)
            ->whereIn('availability_status', [
                DriverAvailabilityStatus::Available->value,
                DriverAvailabilityStatus::Busy->value,
            ])
            ->limit(100)
            ->get();

        foreach ($drivers as $driver) {
            if (! $this->eligibility->isDriverEligibleForOrder($driver, $order)) {
                continue;
            }

            SafeBroadcast::event(new OrderAvailableToDriver($payload, $driver->id));

            if ($kind === 'ready') {
                $this->notifications->driverReady($order, $driver);

                continue;
            }

            $this->notifications->driverOffer($order, $driver);
        }
    }

    /**
     * @return list<string>
     */
    private function audienceForCreated(Order $order): array
    {
        $channels = [
            'order.'.$order->id,
            'admin',
            'customer.'.$order->customer_id,
        ];

        if (! $order->isPlatformManaged() && $order->branch_id !== null) {
            $channels[] = 'branch.'.$order->branch_id;
            $channels[] = 'business.'.$order->branch?->business_id;
        }

        return array_values(array_filter(
            array_unique($channels),
            static fn (?string $channel): bool => filled($channel) && ! str_ends_with((string) $channel, '.'),
        ));
    }

    /**
     * @return list<string>
     */
    private function audienceForStatus(Order $order): array
    {
        $channels = [
            'order.'.$order->id,
            'customer.'.$order->customer_id,
            'admin',
        ];

        if (! $order->isPlatformManaged()) {
            if ($order->branch_id !== null) {
                $channels[] = 'branch.'.$order->branch_id;
            }

            if ($order->branch?->business_id !== null) {
                $channels[] = 'business.'.$order->branch->business_id;
            }
        }

        if ($order->assigned_driver_id !== null) {
            $channels[] = 'driver.'.$order->assigned_driver_id;
        }

        return array_values(array_filter(
            array_unique($channels),
            static fn (?string $channel): bool => filled($channel) && ! str_ends_with((string) $channel, '.'),
        ));
    }

    private function afterCommit(callable $callback): void
    {
        if (DB::transactionLevel() > 0) {
            DB::afterCommit($callback);

            return;
        }

        $callback();
    }
}
