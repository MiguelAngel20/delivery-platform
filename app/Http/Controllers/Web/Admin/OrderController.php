<?php

namespace App\Http\Controllers\Web\Admin;

use App\Actions\Orders\AcceptBusinessOrder;
use App\Actions\Orders\MarkOrderReady;
use App\Actions\Orders\RejectBusinessOrder;
use App\Enums\BusinessOperationMode;
use App\Enums\IncidentStatus;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ConfirmPlatformOrderRequest;
use App\Http\Requests\Admin\ProposeOrderQuoteRequest;
use App\Http\Requests\Admin\RejectPlatformOrderRequest;
use App\Models\Incident;
use App\Models\Order;
use App\Services\Orders\OrderQuoteService;
use App\Support\OrderData;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Order::class);

        $filter = $request->string('filter')->toString();

        $query = Order::query()
            ->with(['branch.business', 'customer.user', 'assignedDriver.user', 'items'])
            ->when(
                filled($request->string('search')->toString()),
                function (Builder $builder) use ($request): void {
                    $search = $request->string('search')->toString();
                    $builder->where(function (Builder $inner) use ($search): void {
                        $inner->where('order_number', 'like', "%{$search}%")
                            ->orWhere('merchant_name_snapshot', 'like', "%{$search}%")
                            ->orWhereHas('customer.user', fn (Builder $userQuery) => $userQuery
                                ->where('name', 'like', "%{$search}%"))
                            ->orWhereHas('branch.business', fn (Builder $businessQuery) => $businessQuery
                                ->where('name', 'like', "%{$search}%"));
                    });
                },
            );

        $this->applyFilter($query, $filter);

        $orders = $query->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Order $order): array => OrderData::listRow($order));

        return Inertia::render('admin/orders/index', [
            'orders' => $orders,
            'filters' => [
                'search' => $request->string('search')->toString(),
                'filter' => $filter,
            ],
            'filterOptions' => $this->filterOptions(),
            'queue' => [
                'pending_platform' => Order::query()
                    ->where('order_status', OrderStatus::PendingPlatform)
                    ->count(),
                'pending_customer_confirmation' => Order::query()
                    ->where('order_status', OrderStatus::PendingCustomerConfirmation)
                    ->count(),
                'open_incidents' => Incident::query()
                    ->where('status', IncidentStatus::Open)
                    ->count(),
            ],
        ]);
    }

    public function show(Order $order): Response
    {
        $this->authorize('view', $order);

        return Inertia::render('admin/orders/show', [
            'order' => OrderData::transform($order),
            'preparationOptions' => [10, 15, 20, 25, 30, 45],
        ]);
    }

    public function confirm(
        ConfirmPlatformOrderRequest $request,
        Order $order,
        AcceptBusinessOrder $action,
    ): RedirectResponse {
        $action->handle(
            $order,
            $request->user(),
            $request->integer('estimated_preparation_minutes'),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Pedido confirmado. Preparación iniciada.',
        ]);

        return back();
    }

    public function reject(
        RejectPlatformOrderRequest $request,
        Order $order,
        RejectBusinessOrder $action,
    ): RedirectResponse {
        $action->handle($order, $request->user(), $request->string('reason')->toString());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Pedido rechazado.',
        ]);

        return back();
    }

    public function markReady(Request $request, Order $order, MarkOrderReady $action): RedirectResponse
    {
        $this->authorize('markReady', $order);
        $action->handle($order, $request->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Pedido marcado como listo.',
        ]);

        return back();
    }

    public function proposeQuote(
        ProposeOrderQuoteRequest $request,
        Order $order,
        OrderQuoteService $quotes,
    ): RedirectResponse {
        $quotes->proposePriceAdjustment(
            $order,
            $request->user(),
            $request->input('items', []),
            $request->input('service_fee'),
            (string) ($request->input('discount_amount') ?? '0'),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Cotización enviada al cliente.',
        ]);

        return back();
    }

    private function applyFilter(Builder $query, string $filter): void
    {
        match ($filter) {
            'partner' => $query
                ->where('type', OrderType::Business)
                ->where('operation_mode', BusinessOperationMode::Partner),
            'ride' => $query
                ->where('type', OrderType::Business)
                ->where('operation_mode', BusinessOperationMode::PlatformOperated),
            'custom' => $query->where('type', OrderType::Custom),
            'pending' => $query->whereIn('order_status', [
                OrderStatus::PendingBusiness,
                OrderStatus::PendingPlatform,
                OrderStatus::PendingCustomerConfirmation,
            ]),
            'preparing' => $query->whereIn('order_status', [
                OrderStatus::Accepted,
                OrderStatus::Preparing,
                OrderStatus::SearchingDriver,
                OrderStatus::ReadyForPickup,
            ]),
            'delivery' => $query->whereIn('order_status', [
                OrderStatus::DriverAssigned,
                OrderStatus::DriverAtBusiness,
                OrderStatus::PickedUp,
                OrderStatus::OnTheWay,
            ]),
            'completed' => $query->where('order_status', OrderStatus::Delivered),
            'cancelled' => $query->whereIn('order_status', [
                OrderStatus::Cancelled,
                OrderStatus::Rejected,
            ]),
            default => null,
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function filterOptions(): array
    {
        return [
            ['value' => '', 'label' => 'Todos'],
            ['value' => 'partner', 'label' => 'Partner'],
            ['value' => 'ride', 'label' => 'Administrados por RIDE'],
            ['value' => 'custom', 'label' => 'Personalizados'],
            ['value' => 'pending', 'label' => 'Pendientes'],
            ['value' => 'preparing', 'label' => 'En preparación'],
            ['value' => 'delivery', 'label' => 'En reparto'],
            ['value' => 'completed', 'label' => 'Finalizados'],
            ['value' => 'cancelled', 'label' => 'Cancelados'],
        ];
    }
}
