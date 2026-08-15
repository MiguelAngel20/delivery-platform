<?php

namespace App\Console\Commands\Reputation;

use App\Models\Customer;
use App\Models\Driver;
use App\Services\Reputation\CustomerReputationService;
use App\Services\Reputation\DriverReputationService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('reputation:recalculate {--customers : Solo clientes} {--drivers : Solo repartidores} {--dry-run : Mostrar conteos sin escribir}')]
#[Description('Rebuild customer_metrics and driver_metrics from order, cancellation and incident history')]
class RecalculateReputationCommand extends Command
{
    public function handle(
        CustomerReputationService $customers,
        DriverReputationService $drivers,
    ): int {
        $onlyCustomers = $this->option('customers');
        $onlyDrivers = $this->option('drivers');
        $runCustomers = $onlyCustomers || (! $onlyCustomers && ! $onlyDrivers);
        $runDrivers = $onlyDrivers || (! $onlyCustomers && ! $onlyDrivers);

        if ($runCustomers) {
            $this->recalculateCustomers($customers);
        }

        if ($runDrivers) {
            $this->recalculateDrivers($drivers);
        }

        return self::SUCCESS;
    }

    private function recalculateCustomers(CustomerReputationService $service): void
    {
        $query = Customer::query()->orderBy('id');
        $count = $query->count();
        $this->info("Clientes a recalcular: {$count}");

        if ($this->option('dry-run')) {
            return;
        }

        $query->each(function (Customer $customer) use ($service): void {
            $metrics = $service->recalculate($customer);
            $this->line("  customer #{$customer->id} · {$metrics->trust_level->value} · score {$metrics->trust_score}");
        });
    }

    private function recalculateDrivers(DriverReputationService $service): void
    {
        $query = Driver::query()->orderBy('id');
        $count = $query->count();
        $this->info("Repartidores a recalcular: {$count}");

        if ($this->option('dry-run')) {
            return;
        }

        $query->each(function (Driver $driver) use ($service): void {
            $metrics = $service->recalculate($driver);
            $this->line("  driver #{$driver->id} · score {$metrics->trust_score} · rating ".($metrics->average_rating ?? 'n/a'));
        });
    }
}
