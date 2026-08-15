<?php

namespace App\Services\Reputation;

use App\Models\Customer;
use App\Models\Driver;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

final class ReputationRecalculator
{
    public function __construct(
        private readonly CustomerReputationService $customers,
        private readonly DriverReputationService $drivers,
    ) {}

    public function forOrder(Order $order): void
    {
        $callback = function () use ($order): void {
            $order->loadMissing(['customer', 'assignedDriver', 'assignments']);

            if ($order->customer instanceof Customer) {
                $this->customers->recalculate($order->customer);
            }

            $driverIds = collect([$order->assigned_driver_id])
                ->merge($order->assignments->pluck('driver_id'))
                ->filter()
                ->unique()
                ->values();

            Driver::query()->whereIn('id', $driverIds)->each(
                fn (Driver $driver) => $this->drivers->recalculate($driver),
            );
        };

        if (DB::transactionLevel() > 0) {
            DB::afterCommit($callback);

            return;
        }

        $callback();
    }

    public function forCustomer(Customer $customer): void
    {
        $this->customers->recalculate($customer);
    }

    public function forDriver(Driver $driver): void
    {
        $this->drivers->recalculate($driver);
    }
}
