<?php

namespace App\Console\Commands\Finance;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\Finance\OrderFinancialService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('finance:backfill-order-financials {--dry-run : List orders without writing}')]
#[Description('Create financial snapshots for delivered orders that predate the finance module')]
class BackfillOrderFinancialsCommand extends Command
{
    public function handle(OrderFinancialService $financials): int
    {
        $orders = Order::query()
            ->with(['assignedDriver', 'branch.business', 'financial'])
            ->where('order_status', OrderStatus::Delivered)
            ->whereNotNull('delivered_at')
            ->whereDoesntHave('financial')
            ->orderBy('id')
            ->get();

        if ($orders->isEmpty()) {
            $this->info('No hay pedidos entregados pendientes de snapshot financiero.');

            return self::SUCCESS;
        }

        $this->info('Pedidos a procesar: '.$orders->count());

        foreach ($orders as $order) {
            $this->line("{$order->order_number} · total {$order->total} · delivered_at {$order->delivered_at}");

            if ($this->option('dry-run')) {
                continue;
            }

            $result = $financials->backfillDeliveredOrder($order);

            $this->line("  snapshot={$result['snapshot']} settlement={$result['settlement']} review={$result['requires_review']}");
        }

        return self::SUCCESS;
    }
}
