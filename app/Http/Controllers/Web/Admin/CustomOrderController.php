<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\CustomOrderRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCustomOrderQuoteRequest;
use App\Models\CustomOrderRequest;
use App\Services\Orders\CustomOrderRequestService;
use App\Services\Orders\OrderQuoteService;
use App\Support\CustomOrderRequestData;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomOrderController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', CustomOrderRequest::class);

        $status = $request->string('status')->toString();

        $requests = CustomOrderRequest::query()
            ->with(['customer.user', 'business', 'assignedAdmin', 'quotes'])
            ->when(
                filled($status),
                fn (Builder $query) => $query->where('status', $status),
            )
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (CustomOrderRequest $customOrder): array => CustomOrderRequestData::listRow($customOrder));

        return Inertia::render('admin/custom-orders/index', [
            'requests' => $requests,
            'filters' => [
                'status' => $status,
            ],
            'statusOptions' => collect(CustomOrderRequestStatus::cases())
                ->map(fn (CustomOrderRequestStatus $case): array => [
                    'value' => $case->value,
                    'label' => $case->label(),
                ])
                ->values()
                ->all(),
            'queue' => [
                'pending_review' => CustomOrderRequest::query()
                    ->where('status', CustomOrderRequestStatus::PendingReview)
                    ->count(),
                'reviewing' => CustomOrderRequest::query()
                    ->where('status', CustomOrderRequestStatus::Reviewing)
                    ->count(),
                'quoted' => CustomOrderRequest::query()
                    ->where('status', CustomOrderRequestStatus::Quoted)
                    ->count(),
            ],
        ]);
    }

    public function show(CustomOrderRequest $customOrder): Response
    {
        $this->authorize('view', $customOrder);

        return Inertia::render('admin/custom-orders/show', [
            'request' => CustomOrderRequestData::transform($customOrder),
            'serviceFee' => (float) config('business.orders.service_fee', 50),
        ]);
    }

    public function claim(
        Request $request,
        CustomOrderRequest $customOrder,
        CustomOrderRequestService $service,
    ): RedirectResponse {
        $this->authorize('claim', $customOrder);
        $service->claim($customOrder, $request->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Solicitud tomada.',
        ]);

        return back();
    }

    public function quote(
        StoreCustomOrderQuoteRequest $httpRequest,
        CustomOrderRequest $customOrder,
        OrderQuoteService $quotes,
    ): RedirectResponse {
        $quotes->createCustomQuote(
            $customOrder,
            $httpRequest->user(),
            $httpRequest->input('items', []),
            $httpRequest->input('service_fee'),
            (string) ($httpRequest->input('discount_amount') ?? '0'),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Cotización enviada al cliente.',
        ]);

        return back();
    }

    public function updatePickup(
        Request $request,
        CustomOrderRequest $customOrder,
    ): RedirectResponse {
        $this->authorize('claim', $customOrder);

        $validated = $request->validate([
            'merchant_address' => ['nullable', 'string', 'max:500'],
            'merchant_latitude' => ['required', 'numeric', 'between:-90,90'],
            'merchant_longitude' => ['required', 'numeric', 'between:-180,180'],
            'merchant_formatted_address' => ['nullable', 'string', 'max:500'],
            'merchant_place_id' => ['nullable', 'string', 'max:255'],
            'merchant_reference' => ['nullable', 'string'],
        ]);

        $customOrder->forceFill($validated)->save();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Ubicación de recogida guardada.',
        ]);

        return back();
    }

    public function reject(
        Request $request,
        CustomOrderRequest $customOrder,
        CustomOrderRequestService $service,
    ): RedirectResponse {
        $this->authorize('reject', $customOrder);

        $request->validate([
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $service->reject($customOrder, $request->user(), $request->input('notes'));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Solicitud rechazada.',
        ]);

        return back();
    }
}
