<?php

namespace App\Jobs\Dispatch;

use App\Enums\DriverApprovalStatus;
use App\Enums\DriverAvailabilityStatus;
use App\Events\Orders\OrderAvailableToDriver;
use App\Models\Driver;
use App\Models\Order;
use App\Services\Dispatch\DriverEligibilityService;
use App\Services\Notifications\RideNotificationDispatcher;
use App\Support\OrderBroadcastPayload;
use App\Support\SafeBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Notifies eligible drivers about an order offer or ready-for-pickup event.
 *
 * Dispatched after the HTTP response so business/admin accept actions feel instant.
 */
final class NotifyEligibleDriversJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  'offer'|'ready'  $kind
     */
    public function __construct(
        public int $orderId,
        public string $kind,
    ) {}

    public function handle(
        DriverEligibilityService $eligibility,
        RideNotificationDispatcher $notifications,
    ): void {
        /** @var Order|null $order */
        $order = Order::query()
            ->with(['branch.business', 'assignedDriver.user'])
            ->find($this->orderId);

        if ($order === null || $order->assigned_driver_id !== null) {
            return;
        }

        $payload = OrderBroadcastPayload::base($order);

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
            if (! $eligibility->isDriverEligibleForOrder($driver, $order)) {
                continue;
            }

            SafeBroadcast::event(new OrderAvailableToDriver($payload, $driver->id));

            if ($this->kind === 'ready') {
                $notifications->driverReady($order, $driver);

                continue;
            }

            $notifications->driverOffer($order, $driver);
        }
    }
}
