<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Driver;
use App\Models\Order;
use App\Services\Reputation\CustomerReputationService;
use App\Services\Reputation\DriverReputationService;
use Illuminate\Database\Seeder;

class ReputationDemoSeeder extends Seeder
{
    public function run(
        CustomerReputationService $customers,
        DriverReputationService $drivers,
    ): void {
        if (app()->isProduction()) {
            return;
        }

        Customer::query()->orderBy('id')->each(
            fn (Customer $customer) => $customers->recalculate($customer),
        );

        Driver::query()->orderBy('id')->each(
            fn (Driver $driver) => $drivers->recalculate($driver),
        );

        if (! app()->environment(['local', 'development'])) {
            return;
        }

        $order = Order::query()
            ->where('order_status', OrderStatus::Delivered)
            ->whereNotNull('assigned_driver_id')
            ->whereDoesntHave('driverRating')
            ->first();

        if ($order === null || $order->customer === null) {
            return;
        }

        $drivers->rate($order, $order->customer, [
            'overall_rating' => 5,
            'comment' => 'Calificación de demostración.',
        ]);
    }
}
