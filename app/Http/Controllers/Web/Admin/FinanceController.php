<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\SettlementStatus;
use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Driver;
use App\Models\Order;
use App\Support\OrderData;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class FinanceController extends Controller
{
    public function index(Request $request): Response
    {
        $from = $request->date('from') ?? Carbon::today()->startOfMonth();
        $to = $request->date('to') ?? Carbon::today()->endOfDay();

        $query = $this->filteredDeliveredOrders($request, $from, $to);

        $orders = (clone $query)
            ->with(['financial', 'branch.business', 'assignedDriver.user', 'payment'])
            ->latest('delivered_at')
            ->paginate(20)
            ->withQueryString();

        $totals = (clone $query)
            ->join('order_financials', 'order_financials.order_id', '=', 'orders.id')
            ->selectRaw('COUNT(orders.id) as delivered_orders')
            ->selectRaw('COALESCE(SUM(order_financials.service_fee), 0) as service_income')
            ->selectRaw('COALESCE(SUM(order_financials.driver_earning), 0) as driver_earnings')
            ->selectRaw('COALESCE(SUM(order_financials.business_amount), 0) as business_amount')
            ->first();

        $pendingSettlement = (clone $query)
            ->whereHas('financial', fn (Builder $q) => $q->whereIn('settlement_status', [
                SettlementStatus::Open->value,
                SettlementStatus::PartiallySettled->value,
                SettlementStatus::RequiresReview->value,
            ]))
            ->count();

        return Inertia::render('admin/finance/index', [
            'summary' => [
                'delivered_orders' => (int) ($totals->delivered_orders ?? 0),
                'service_income' => number_format((float) ($totals->service_income ?? 0), 2, '.', ''),
                'driver_earnings' => number_format((float) ($totals->driver_earnings ?? 0), 2, '.', ''),
                'business_amount' => number_format((float) ($totals->business_amount ?? 0), 2, '.', ''),
                'pending_settlement' => $pendingSettlement,
            ],
            'orders' => $orders->through(fn (Order $order): array => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'delivered_at' => $order->delivered_at?->toIso8601String(),
                'business_name' => $order->branch?->business?->name,
                'driver_name' => $order->assignedDriver?->user?->name,
                'service_fee' => (string) ($order->financial?->service_fee ?? '0.00'),
                'driver_earning' => (string) ($order->financial?->driver_earning ?? '0.00'),
                'business_amount' => (string) ($order->financial?->business_amount ?? '0.00'),
                'customer_total' => (string) ($order->financial?->customer_total ?? '0.00'),
                'payment_method_label' => $order->financial?->payment_method?->label()
                    ?? $order->payment_method->label(),
                'settlement_status' => $order->financial?->settlement_status?->value,
                'settlement_status_label' => $order->financial?->settlement_status?->label(),
            ]),
            'filters' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'business_id' => $request->input('business_id', ''),
                'driver_id' => $request->input('driver_id', ''),
                'settlement_status' => $request->input('settlement_status', ''),
                'payment_method' => $request->input('payment_method', ''),
            ],
            'filterOptions' => [
                'businesses' => Business::query()->orderBy('name')->get(['id', 'name'])
                    ->map(fn (Business $business): array => [
                        'value' => (string) $business->id,
                        'label' => $business->name,
                    ])->values()->all(),
                'drivers' => Driver::query()->with('user')->orderBy('id')->limit(200)->get()
                    ->map(fn (Driver $driver): array => [
                        'value' => (string) $driver->id,
                        'label' => $driver->user?->name ?? "Driver #{$driver->id}",
                    ])->values()->all(),
                'settlementStatuses' => collect(SettlementStatus::cases())->map(fn (SettlementStatus $status): array => [
                    'value' => $status->value,
                    'label' => $status->label(),
                ])->values()->all(),
                'paymentMethods' => collect(PaymentMethod::cases())
                    ->filter(fn (PaymentMethod $method): bool => $method->isEnabledInV1())
                    ->map(fn (PaymentMethod $method): array => [
                        'value' => $method->value,
                        'label' => $method->label(),
                    ])->values()->all(),
            ],
        ]);
    }

    public function show(Order $order): Response
    {
        $order->loadMissing([
            'financial',
            'financialTransactions',
            'payment',
            'branch.business',
            'assignedDriver.user',
            'customer.user',
            'items.options',
            'addresses',
            'statusHistory',
        ]);

        return Inertia::render('admin/finance/show', [
            'order' => OrderData::transform($order),
            'financial' => OrderData::financialDetail($order),
        ]);
    }

    /**
     * @return Builder<Order>
     */
    private function filteredDeliveredOrders(Request $request, Carbon $from, Carbon $to): Builder
    {
        $query = Order::query()
            ->where('orders.order_status', OrderStatus::Delivered)
            ->whereNotNull('orders.delivered_at')
            ->whereBetween('orders.delivered_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->whereHas('financial');

        if (filled($request->input('business_id'))) {
            $businessId = (int) $request->input('business_id');
            $query->whereHas('branch', fn (Builder $q) => $q->where('business_id', $businessId));
        }

        if (filled($request->input('driver_id'))) {
            $query->where('orders.assigned_driver_id', (int) $request->input('driver_id'));
        }

        if (filled($request->input('settlement_status'))) {
            $status = $request->string('settlement_status')->toString();
            $query->whereHas('financial', fn (Builder $q) => $q->where('settlement_status', $status));
        }

        if (filled($request->input('payment_method'))) {
            $method = $request->string('payment_method')->toString();
            $query->whereHas('financial', fn (Builder $q) => $q->where('payment_method', $method));
        }

        return $query;
    }
}
