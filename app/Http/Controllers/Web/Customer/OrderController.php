<?php

namespace App\Http\Controllers\Web\Customer;

use App\Actions\Orders\CreateOrder;
use App\Enums\CancellationReasonCode;
use App\Enums\IncidentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\CancelOrderRequest;
use App\Http\Requests\Customer\ReportIncidentRequest;
use App\Http\Requests\Customer\StoreOrderRequest;
use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use App\Services\Incidents\IncidentService;
use App\Services\Orders\OrderCancellationService;
use App\Services\Orders\OrderQuoteService;
use App\Support\OrderData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    public function index(Request $request): Response
    {
        $customer = $this->currentCustomer($request);
        $this->authorize('viewAny', Order::class);

        $active = Order::query()
            ->where('customer_id', $customer->id)
            ->with(['branch.business', 'items'])
            ->whereNotIn('order_status', ['cancelled', 'rejected', 'delivered'])
            ->latest()
            ->get()
            ->map(fn (Order $order): array => OrderData::listRow($order));

        $history = Order::query()
            ->where('customer_id', $customer->id)
            ->with(['branch.business', 'items'])
            ->whereIn('order_status', ['cancelled', 'rejected', 'delivered'])
            ->latest()
            ->paginate(10)
            ->withQueryString()
            ->through(fn (Order $order): array => OrderData::listRow($order));

        return Inertia::render('customer/orders/index', [
            'activeOrders' => $active,
            'historyOrders' => $history,
        ]);
    }

    public function show(Request $request, Order $order): Response
    {
        $this->ensureOwns($request, $order);
        $this->authorize('view', $order);

        return Inertia::render('customer/orders/show', [
            'order' => OrderData::transform($order),
        ]);
    }

    public function cancel(
        CancelOrderRequest $request,
        Order $order,
        OrderCancellationService $cancellations,
    ): RedirectResponse {
        $this->ensureOwns($request, $order);

        $cancellations->cancelByCustomer(
            $order,
            $request->user(),
            CancellationReasonCode::from($request->string('reason_code')->toString()),
            $request->input('reason'),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Pedido cancelado.',
        ]);

        return back();
    }

    public function reportIncident(
        ReportIncidentRequest $request,
        Order $order,
        IncidentService $incidents,
    ): RedirectResponse {
        $this->ensureOwns($request, $order);

        $incidents->report($order, $request->user(), [
            'type' => IncidentType::from($request->string('type')->toString()),
            'description' => $request->string('description')->toString(),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Incidencia enviada. Un administrador la revisará.',
        ]);

        return back();
    }

    public function store(StoreOrderRequest $request, CreateOrder $action): RedirectResponse
    {
        $customer = $this->currentCustomer($request);

        $order = $action->handle($customer, $request->user(), $request->validated());

        // Toast is shown client-side on checkout success (more reliable across
        // full-page redirects from Inertia asset version mismatches).

        return to_route('customer.orders.show', $order);
    }

    public function acceptQuote(
        Request $request,
        Order $order,
        OrderQuoteService $quotes,
    ): RedirectResponse {
        $this->ensureOwns($request, $order);
        $this->authorize('acceptQuote', $order);

        $quotes->acceptPriceAdjustment($order, $request->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Nuevo total aceptado.',
        ]);

        return back();
    }

    private function currentCustomer(Request $request): Customer
    {
        /** @var User $user */
        $user = $request->user();
        $customer = $user->customer;

        abort_unless($customer !== null, 403);

        return $customer;
    }

    private function ensureOwns(Request $request, Order $order): void
    {
        abort_unless(
            $request->user()?->customer?->id === $order->customer_id,
            404,
        );
    }
}
