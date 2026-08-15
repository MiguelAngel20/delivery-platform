<?php

namespace App\Http\Controllers\Web\Driver;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class EarningsController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $driver = $user->driver;

        abort_if($driver === null, 403);

        $todayStart = Carbon::today();
        $weekStart = Carbon::today()->startOfWeek();

        $baseQuery = Order::query()
            ->where('orders.assigned_driver_id', $driver->id)
            ->where('orders.order_status', OrderStatus::Delivered)
            ->whereNotNull('orders.delivered_at')
            ->whereHas('financial');

        $todayEarnings = (clone $baseQuery)
            ->where('orders.delivered_at', '>=', $todayStart)
            ->join('order_financials', 'order_financials.order_id', '=', 'orders.id')
            ->sum('order_financials.driver_earning');

        $weekEarnings = (clone $baseQuery)
            ->where('orders.delivered_at', '>=', $weekStart)
            ->join('order_financials', 'order_financials.order_id', '=', 'orders.id')
            ->sum('order_financials.driver_earning');

        $completedCount = (clone $baseQuery)->count();

        $orders = (clone $baseQuery)
            ->with(['financial', 'branch.business'])
            ->latest('delivered_at')
            ->limit(50)
            ->get()
            ->map(fn (Order $order): array => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'business_name' => $order->branch?->business?->name ?? '—',
                'delivered_at' => $order->delivered_at?->toIso8601String(),
                'driver_earning' => (string) ($order->financial?->driver_earning ?? '0.00'),
                'status_label' => $order->order_status->label(),
            ]);

        return Inertia::render('driver/earnings/index', [
            'summary' => [
                'today' => number_format((float) $todayEarnings, 2, '.', ''),
                'week' => number_format((float) $weekEarnings, 2, '.', ''),
                'completed_orders' => $completedCount,
            ],
            'orders' => $orders,
        ]);
    }
}
