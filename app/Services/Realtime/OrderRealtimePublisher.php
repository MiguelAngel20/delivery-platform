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
                broadcast(new OrderCreated($payload, $channels));
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
                broadcast(new OrderStatusChanged($payload, $channels));
            }

            $this->notifications->statusChanged($order, $previous);

            if (in_array($order->order_status, [
                OrderStatus::Preparing,
                OrderStatus::SearchingDriver,
                OrderStatus::ReadyForPickup,
            ], true) && $order->assigned_driver_id === null) {
                $this->notifyEligibleDrivers($order, $payload);
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
                broadcast(new DriverAssigned($payload, $channels));
                broadcast(new OrderStatusChanged($payload, $channels));
            }

            $this->notifications->driverAssigned($order);
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function notifyEligibleDrivers(Order $order, array $payload): void
    {
        $drivers = Driver::query()
            ->with(['user', 'businesses'])
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

            broadcast(new OrderAvailableToDriver($payload, $driver->id));
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
