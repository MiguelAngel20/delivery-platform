<?php

namespace App\Http\Controllers\Web\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\StoreCustomOrderRequest;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\CustomOrderRequest;
use App\Models\User;
use App\Services\Orders\CustomOrderRequestService;
use App\Services\Orders\OrderQuoteService;
use App\Support\CustomOrderRequestData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomOrderController extends Controller
{
    public function index(Request $request): Response
    {
        $customer = $this->currentCustomer($request);
        $this->authorize('viewAny', CustomOrderRequest::class);

        $requests = CustomOrderRequest::query()
            ->where('customer_id', $customer->id)
            ->with(['quotes.items', 'quotedOrder'])
            ->latest()
            ->paginate(10)
            ->withQueryString()
            ->through(fn (CustomOrderRequest $customOrder): array => CustomOrderRequestData::listRow($customOrder));

        return Inertia::render('customer/custom-orders/index', [
            'requests' => $requests,
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', CustomOrderRequest::class);
        $customer = $this->currentCustomer($request);

        $addresses = $customer->addresses()
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('label')
            ->get()
            ->map(fn (CustomerAddress $address): array => [
                'id' => (string) $address->id,
                'label' => $address->label,
                'line' => $address->address_text,
                'address_text' => $address->address_text,
                'reference' => $address->reference,
                'latitude' => (string) $address->latitude,
                'longitude' => (string) $address->longitude,
                'isDefault' => $address->is_default,
            ]);

        return Inertia::render('customer/custom-orders/create', [
            'addresses' => $addresses,
        ]);
    }

    public function store(
        StoreCustomOrderRequest $request,
        CustomOrderRequestService $service,
    ): RedirectResponse {
        $customer = $this->currentCustomer($request);

        $customOrder = $service->create($customer, $request->user(), $request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Solicitud enviada. Te avisaremos cuando haya una cotización.',
        ]);

        return to_route('customer.custom-orders.show', $customOrder);
    }

    public function show(Request $request, CustomOrderRequest $customOrder): Response
    {
        $this->ensureOwns($request, $customOrder);
        $this->authorize('view', $customOrder);

        return Inertia::render('customer/custom-orders/show', [
            'request' => CustomOrderRequestData::transform($customOrder),
        ]);
    }

    public function acceptQuote(
        Request $request,
        CustomOrderRequest $customOrder,
        OrderQuoteService $quotes,
    ): RedirectResponse {
        $this->ensureOwns($request, $customOrder);
        $this->authorize('acceptQuote', $customOrder);

        $order = $quotes->acceptCustomQuote($customOrder, $request->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Cotización aceptada. Pedido creado.',
        ]);

        return to_route('customer.orders.show', $order);
    }

    public function rejectQuote(
        Request $request,
        CustomOrderRequest $customOrder,
        OrderQuoteService $quotes,
    ): RedirectResponse {
        $this->ensureOwns($request, $customOrder);
        $this->authorize('rejectQuote', $customOrder);

        $quotes->rejectCustomQuote($customOrder, $request->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Cotización rechazada.',
        ]);

        return back();
    }

    public function cancel(
        Request $request,
        CustomOrderRequest $customOrder,
        CustomOrderRequestService $service,
    ): RedirectResponse {
        $this->ensureOwns($request, $customOrder);
        $this->authorize('cancel', $customOrder);

        $service->cancelByCustomer($customOrder, $this->currentCustomer($request));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Solicitud cancelada.',
        ]);

        return to_route('customer.custom-orders.index');
    }

    private function currentCustomer(Request $request): Customer
    {
        /** @var User $user */
        $user = $request->user();
        $customer = $user->customer;

        abort_unless($customer !== null, 403);

        return $customer;
    }

    private function ensureOwns(Request $request, CustomOrderRequest $customOrder): void
    {
        abort_unless(
            $request->user()?->customer?->id === $customOrder->customer_id,
            404,
        );
    }
}
