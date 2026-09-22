<?php

namespace App\Http\Controllers\Web\Driver;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use App\Services\Drivers\DriverCommissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class EarningsController extends Controller
{
    public function index(Request $request, DriverCommissionService $commissions): Response
    {
        /** @var User $user */
        $user = $request->user();
        $driver = $user->driver;

        abort_if($driver === null, 403);

        $from = $this->parseDate($request->input('from')) ?? Carbon::today()->subDays(6)->startOfDay();
        $to = $this->parseDate($request->input('to'))?->endOfDay() ?? Carbon::today()->endOfDay();

        if ($from->greaterThan($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        $baseQuery = Order::query()
            ->where('orders.assigned_driver_id', $driver->id)
            ->where('orders.order_status', OrderStatus::Delivered)
            ->whereNotNull('orders.delivered_at')
            ->whereBetween('orders.delivered_at', [$from, $to])
            ->whereHas('financial');

        $todayStart = Carbon::today();
        $weekStart = Carbon::today()->startOfWeek();

        $allDelivered = Order::query()
            ->where('orders.assigned_driver_id', $driver->id)
            ->where('orders.order_status', OrderStatus::Delivered)
            ->whereNotNull('orders.delivered_at')
            ->whereHas('financial');

        $todayEarnings = (clone $allDelivered)
            ->where('orders.delivered_at', '>=', $todayStart)
            ->join('order_financials', 'order_financials.order_id', '=', 'orders.id')
            ->sum('order_financials.driver_earning');

        $weekEarnings = (clone $allDelivered)
            ->where('orders.delivered_at', '>=', $weekStart)
            ->join('order_financials', 'order_financials.order_id', '=', 'orders.id')
            ->sum('order_financials.driver_earning');

        $rangeNet = (clone $baseQuery)
            ->join('order_financials', 'order_financials.order_id', '=', 'orders.id')
            ->sum('order_financials.driver_earning');

        $rangeCommission = (clone $baseQuery)
            ->join('order_financials', 'order_financials.order_id', '=', 'orders.id')
            ->sum('order_financials.driver_commission');

        $completedCount = (clone $baseQuery)->count();
        $owedToPlatform = $commissions->unpaidAmount($driver);

        $orders = (clone $baseQuery)
            ->with(['financial', 'branch.business'])
            ->latest('delivered_at')
            ->limit(100)
            ->get()
            ->map(function (Order $order): array {
                $commission = (string) ($order->financial?->driver_commission ?? '0.00');
                $net = (string) ($order->financial?->driver_earning ?? '0.00');
                $gross = bcadd($net, $commission, 2);

                return [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'business_name' => $order->branch?->business?->name ?? '—',
                    'delivered_at' => $order->delivered_at?->toIso8601String(),
                    'gross_earning' => $gross,
                    'driver_commission' => number_format((float) $commission, 2, '.', ''),
                    'driver_earning' => number_format((float) $net, 2, '.', ''),
                    'chisdrive_share' => number_format((float) $commission, 2, '.', ''),
                    'status_label' => $order->order_status->label(),
                ];
            });

        return Inertia::render('driver/earnings/index', [
            'filters' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
            'summary' => [
                'today' => number_format((float) $todayEarnings, 2, '.', ''),
                'week' => number_format((float) $weekEarnings, 2, '.', ''),
                'range_net' => number_format((float) $rangeNet, 2, '.', ''),
                'range_commission' => number_format((float) $rangeCommission, 2, '.', ''),
                'owed_to_chisdrive' => $owedToPlatform,
                'completed_orders' => $completedCount,
                'pays_commission' => (bool) $driver->pays_commission,
                'commission_per_order' => number_format((float) $driver->commission_per_order, 2, '.', ''),
            ],
            'orders' => $orders,
        ]);
    }

    private function parseDate(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
