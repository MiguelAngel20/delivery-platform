<?php

namespace App\Console\Commands\Reputation;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('reputation:recalculate-customers {--dry-run : Mostrar conteos sin escribir}')]
#[Description('Rebuild customer_metrics from historical orders, cancellations and incidents')]
class RecalculateCustomerMetricsCommand extends Command
{
    public function handle(): int
    {
        return $this->call('reputation:recalculate', [
            '--customers' => true,
            '--dry-run' => (bool) $this->option('dry-run'),
        ]);
    }
}
