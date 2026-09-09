<?php

namespace App\Http\Controllers\Web\Driver;

use App\Actions\Dispatch\AcceptDeliveryOrder;
use App\Actions\Dispatch\DeliverOrder;
use App\Actions\Dispatch\MarkDriverArrived;
use App\Actions\Dispatch\PickupOrder;
use App\Actions\Dispatch\RejectDeliveryOffer;
use App\Actions\Dispatch\StartDelivery;
use App\Enums\CancellationReasonCode;
use App\Enums\IncidentType;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Driver\CannotContinueRequest;
use App\Http\Requests\Driver\ReportIncidentRequest;
use App\Models\Driver;
use App\Models\Order;
use App\Models\User;
use App\Services\Dispatch\AvailableOrdersQuery;
use App\Services\Dispatch\DriverActiveOrderService;
use App\Services\Dispatch\DriverRankingService;
use App\Services\Incidents\IncidentService;
use App\Services\Orders\OrderCancellationService;
use App\Support\OrderData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    public function show(Request $request, Order $order): Response
    {
        $this->authorize('view', $order);

        $driver = $this->currentDriver($request);

        abort_unless(
            (int) $order->assigned_driver_id === (int) $driver->id
            && $order->order_status === OrderStatus::Delivered,
            404,
        );

        $order->loadMissing(['branch.business', 'financial', 'driverRating']);

        return Inertia::render('driver/orders/show', [
            'order' => OrderData::forDriverCompleted($order),
        ]);
    }

    public function index(
        Request $request,
        AvailableOrdersQuery $availableOrders,
        DriverActiveOrderService $activeOrders,
        DriverRankingService $ranking,
    ): Response {
        $this->authorize('viewAvailable', Order::class);

        $driver = $this->currentDriver($request);

        return Inertia::render('driver/orders/index', [
            'availableOrders' => $availableOrders->forDriver($driver)
                ->map(fn (Order $order): array => OrderData::driverAvailableCard(
                    $order,
                    $ranking->distanceToPickupMeters($driver, $order),
                ))
                ->values()
                ->all(),
            'activeOrders' => $activeOrders->activeOrdersFor($driver)
                ->map(fn (Order $order): array => OrderData::driverActiveCard($order))
                ->values()
                ->all(),
        ]);
    }

    public function accept(
        Request $request,
        Order $order,
        AcceptDeliveryOrder $action,
    ): RedirectResponse {
        $this->authorize('acceptDelivery', $order);

        $action->handle($order, $this->currentDriver($request), $request->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Pedido aceptado.',
        ]);

        return back();
    }

    public function reject(
        Request $request,
        Order $order,
        RejectDeliveryOffer $action,
    ): RedirectResponse {
        $this->authorize('rejectDelivery', $order);

        $action->handle($order, $this->currentDriver($request));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Pedido rechazado.',
        ]);

        return back();
    }

    public function arrive(
        Request $request,
        Order $order,
        MarkDriverArrived $action,
    ): RedirectResponse {
        $this->authorize('manageDelivery', $order);

        $action->handle($order, $this->currentDriver($request), $request->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Llegada registrada.',
        ]);

        return back();
    }

    public function pickup(
        Request $request,
        Order $order,
        PickupOrder $action,
    ): RedirectResponse {
        $this->authorize('manageDelivery', $order);

        $action->handle($order, $this->currentDriver($request), $request->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'En camino al cliente.',
        ]);

        return back();
    }

    public function startDelivery(
        Request $request,
        Order $order,
        StartDelivery $action,
    ): RedirectResponse {
        $this->authorize('manageDelivery', $order);

        $action->handle($order, $this->currentDriver($request), $request->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Cliente notificado: estás afuera de su domicilio.',
        ]);

        return back();
    }

    public function deliver(
        Request $request,
        Order $order,
        DeliverOrder $action,
    ): RedirectResponse {
        $this->authorize('manageDelivery', $order);

        $action->handle($order, $this->currentDriver($request), $request->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Pedido entregado.',
        ]);

        return back();
    }

    public function cannotContinue(
        CannotContinueRequest $request,
        Order $order,
        OrderCancellationService $cancellations,
    ): RedirectResponse {
        $this->authorize('manageDelivery', $order);

        $cancellations->driverCannotContinue(
            $order,
            $this->currentDriver($request),
            $request->user(),
            CancellationReasonCode::from($request->string('reason_code')->toString()),
            $request->string('description')->toString(),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Incidencia registrada.',
        ]);

        return redirect()->route('driver.home');
    }

    public function reportIncident(
        ReportIncidentRequest $request,
        Order $order,
        IncidentService $incidents,
    ): RedirectResponse {
        $this->authorize('reportIncident', $order);

        $incidents->report($order, $request->user(), [
            'type' => IncidentType::from($request->string('type')->toString()),
            'description' => $request->string('description')->toString(),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Problema reportado.',
        ]);

        return back();
    }

    private function currentDriver(Request $request): Driver
    {
        /** @var User $user */
        $user = $request->user();
        $driver = $user->driver;

        abort_unless($driver !== null, 403);

        return $driver;
    }
}
