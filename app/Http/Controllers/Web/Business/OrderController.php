<?php

namespace App\Http\Controllers\Web\Business;

use App\Actions\Orders\AcceptBusinessOrder;
use App\Actions\Orders\MarkOrderReady;
use App\Actions\Orders\RejectBusinessOrder;
use App\Enums\BusinessOperationMode;
use App\Enums\CancellationReasonCode;
use App\Enums\IncidentType;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Business\AcceptOrderRequest;
use App\Http\Requests\Business\CancelOrderRequest;
use App\Http\Requests\Business\RejectOrderRequest;
use App\Http\Requests\Business\ReportIncidentRequest;
use App\Models\Order;
use App\Models\User;
use App\Services\Incidents\IncidentService;
use App\Services\Orders\OrderCancellationService;
use App\Support\BusinessAccess;
use App\Support\OrderData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    public function __construct(
        private readonly BusinessAccess $businessAccess,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Order::class);

        /** @var User $user */
        $user = $request->user();
        $branchIds = $this->businessAccess->accessibleBranches($user)->pluck('id');

        $orders = Order::query()
            ->whereIn('branch_id', $branchIds)
            ->where('type', OrderType::Business)
            ->where('operation_mode', BusinessOperationMode::Partner)
            ->with(['branch.business', 'customer.user', 'items.options'])
            ->when(
                filled($request->string('search')->toString()),
                function ($query) use ($request): void {
                    $search = $request->string('search')->toString();
                    $query->where(function ($inner) use ($search): void {
                        $inner->where('order_number', 'like', "%{$search}%")
                            ->orWhereHas('customer.user', fn ($userQuery) => $userQuery
                                ->where('name', 'like', "%{$search}%"));
                    });
                },
            )
            ->when(
                filled($request->input('status')),
                fn ($query) => $query->where('order_status', $request->string('status')->toString()),
            )
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Order $order): array => OrderData::transform($order));

        $newCount = Order::query()
            ->whereIn('branch_id', $branchIds)
            ->where('type', OrderType::Business)
            ->where('operation_mode', BusinessOperationMode::Partner)
            ->where('order_status', OrderStatus::PendingBusiness)
            ->count();

        return Inertia::render('business/orders/index', [
            'orders' => $orders,
            'newCount' => $newCount,
            'filters' => [
                'search' => $request->string('search')->toString(),
                'status' => $request->input('status', ''),
            ],
            'statusOptions' => collect(OrderStatus::cases())
                ->filter(fn (OrderStatus $status) => in_array($status, [
                    OrderStatus::PendingBusiness,
                    OrderStatus::Preparing,
                    OrderStatus::ReadyForPickup,
                    OrderStatus::DriverAssigned,
                    OrderStatus::DriverAtBusiness,
                    OrderStatus::PickedUp,
                    OrderStatus::OnTheWay,
                    OrderStatus::Delivered,
                    OrderStatus::Rejected,
                    OrderStatus::Cancelled,
                ], true))
                ->map(fn (OrderStatus $status): array => [
                    'value' => $status->value,
                    'label' => $status->label(),
                ])
                ->values()
                ->all(),
        ]);
    }

    public function show(Request $request, Order $order): Response
    {
        $this->ensureAccessible($request, $order);
        $this->authorize('view', $order);

        return Inertia::render('business/orders/show', [
            'order' => OrderData::transform($order),
            'preparationOptions' => [10, 15, 20, 30, 45],
        ]);
    }

    public function accept(
        AcceptOrderRequest $request,
        Order $order,
        AcceptBusinessOrder $action,
    ): RedirectResponse {
        $this->ensureAccessible($request, $order);

        $action->handle(
            $order,
            $request->user(),
            $request->integer('estimated_preparation_minutes'),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Pedido aceptado. Preparación iniciada.',
        ]);

        return back();
    }

    public function reject(
        RejectOrderRequest $request,
        Order $order,
        RejectBusinessOrder $action,
    ): RedirectResponse {
        $this->ensureAccessible($request, $order);

        $action->handle($order, $request->user(), $request->string('reason')->toString());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Pedido rechazado.',
        ]);

        return back();
    }

    public function markReady(
        Request $request,
        Order $order,
        MarkOrderReady $action,
    ): RedirectResponse {
        $this->ensureAccessible($request, $order);
        $this->authorize('markReady', $order);

        $action->handle($order, $request->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Pedido marcado como listo.',
        ]);

        return back();
    }

    public function cancel(
        CancelOrderRequest $request,
        Order $order,
        OrderCancellationService $cancellations,
    ): RedirectResponse {
        $this->ensureAccessible($request, $order);

        $cancellations->cancelByBusiness(
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
        $this->ensureAccessible($request, $order);

        $incidents->report($order, $request->user(), [
            'type' => IncidentType::from($request->string('type')->toString()),
            'description' => $request->string('description')->toString(),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Incidencia enviada.',
        ]);

        return back();
    }

    private function ensureAccessible(Request $request, Order $order): void
    {
        /** @var User $user */
        $user = $request->user();
        $order->loadMissing('branch');

        abort_unless(
            $order->branch !== null
                && ! $order->isPlatformManaged()
                && $this->businessAccess->canAccessBranch($user, $order->branch),
            404,
        );
    }
}
