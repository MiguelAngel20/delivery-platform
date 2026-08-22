<?php

namespace App\Http\Controllers\Web\Business;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use App\Support\BusinessAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class FinanceController extends Controller
{
    public function __construct(
        private readonly BusinessAccess $businessAccess,
    ) {}

    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($user->role === UserRole::BusinessAdmin, 403);

        $membership = $this->businessAccess->activeMembership($user);
        abort_if($membership?->business === null, 403);

        $branchIds = $this->businessAccess->accessibleBranches($user)->pluck('id');

        $from = $request->date('from') ?? Carbon::today();
        $to = $request->date('to') ?? Carbon::today();

        $base = Order::query()
            ->whereIn('orders.branch_id', $branchIds)
            ->where('orders.order_status', OrderStatus::Delivered)
            ->whereNotNull('orders.delivered_at')
            ->whereBetween('orders.delivered_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->whereHas('financial');

        $orders = (clone $base)
            ->with(['financial', 'branch.business'])
            ->latest('delivered_at')
            ->paginate(20)
            ->withQueryString();

        $totals = (clone $base)
            ->join('order_financials', 'order_financials.order_id', '=', 'orders.id')
            ->selectRaw('COUNT(orders.id) as completed_orders')
            ->selectRaw('COALESCE(SUM(order_financials.business_amount), 0) as products_amount')
            ->selectRaw('COALESCE(SUM(order_financials.service_fee), 0) as service_fee')
            ->selectRaw('COALESCE(SUM(order_financials.customer_total), 0) as customer_total')
            ->first();

        return Inertia::render('business/finance/index', [
            'summary' => [
                'completed_orders' => (int) ($totals->completed_orders ?? 0),
                'products_amount' => number_format((float) ($totals->products_amount ?? 0), 2, '.', ''),
                'service_fee' => number_format((float) ($totals->service_fee ?? 0), 2, '.', ''),
                'customer_total' => number_format((float) ($totals->customer_total ?? 0), 2, '.', ''),
            ],
            'orders' => $orders->through(fn (Order $order): array => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'delivered_at' => $order->delivered_at?->toIso8601String(),
                'branch_name' => $order->branch?->name,
                'products_amount' => (string) ($order->financial?->business_amount ?? '0.00'),
                'service_fee' => (string) ($order->financial?->service_fee ?? '0.00'),
                'customer_total' => (string) ($order->financial?->customer_total ?? '0.00'),
            ]),
            'filters' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
        ]);
    }
}
