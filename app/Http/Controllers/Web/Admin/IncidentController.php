<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\CancellationReasonCode;
use App\Enums\CancellationResponsibility;
use App\Enums\IncidentSeverity;
use App\Enums\IncidentStatus;
use App\Enums\IncidentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CancelOrderRequest;
use App\Http\Requests\Admin\ResolveIncidentRequest;
use App\Http\Requests\Admin\ReviewCancellationRequest;
use App\Models\Business;
use App\Models\Incident;
use App\Models\Order;
use App\Models\OrderCancellation;
use App\Services\Incidents\IncidentService;
use App\Services\Orders\OrderCancellationService;
use App\Support\OrderData;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class IncidentController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Incident::class);

        $from = $request->date('from');
        $to = $request->date('to');

        $query = Incident::query()
            ->with(['order.branch.business', 'reportedBy', 'business'])
            ->when(
                filled($request->input('status')),
                fn (Builder $q) => $q->where('status', $request->string('status')->toString()),
            )
            ->when(
                filled($request->input('type')),
                fn (Builder $q) => $q->where('type', $request->string('type')->toString()),
            )
            ->when(
                filled($request->input('severity')),
                fn (Builder $q) => $q->where('severity', $request->string('severity')->toString()),
            )
            ->when(
                filled($request->input('business_id')),
                fn (Builder $q) => $q->where('business_id', (int) $request->input('business_id')),
            )
            ->when(
                $from !== null,
                fn (Builder $q) => $q->where('created_at', '>=', $from->copy()->startOfDay()),
            )
            ->when(
                $to !== null,
                fn (Builder $q) => $q->where('created_at', '<=', $to->copy()->endOfDay()),
            );

        $incidents = $query->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Incident $incident): array => $this->listRow($incident));

        return Inertia::render('admin/incidents/index', [
            'incidents' => $incidents,
            'filters' => [
                'status' => $request->input('status', ''),
                'type' => $request->input('type', ''),
                'severity' => $request->input('severity', ''),
                'business_id' => $request->input('business_id', ''),
                'from' => $from?->toDateString() ?? '',
                'to' => $to?->toDateString() ?? '',
            ],
            'filterOptions' => [
                'statuses' => collect(IncidentStatus::cases())->map(fn (IncidentStatus $status): array => [
                    'value' => $status->value,
                    'label' => $status->label(),
                ])->values()->all(),
                'types' => collect(IncidentType::cases())->map(fn (IncidentType $type): array => [
                    'value' => $type->value,
                    'label' => $type->label(),
                ])->values()->all(),
                'severities' => collect(IncidentSeverity::cases())->map(fn (IncidentSeverity $severity): array => [
                    'value' => $severity->value,
                    'label' => $severity->label(),
                ])->values()->all(),
                'businesses' => Business::query()->orderBy('name')->get(['id', 'name'])
                    ->map(fn (Business $business): array => [
                        'value' => (string) $business->id,
                        'label' => $business->name,
                    ])->values()->all(),
            ],
        ]);
    }

    public function show(Incident $incident): Response
    {
        $this->authorize('view', $incident);

        $incident->loadMissing([
            'order.financial',
            'order.financialTransactions',
            'order.payment',
            'order.cancellation.cancelledBy',
            'order.cancellation.reviewedBy',
            'order.statusHistory',
            'order.items.options',
            'order.addresses',
            'order.branch.business',
            'order.customer.user',
            'order.assignedDriver.user',
            'reportedBy',
            'resolvedBy',
            'customer.user',
            'driver.user',
            'business',
        ]);

        return Inertia::render('admin/incidents/show', [
            'incident' => [
                'id' => $incident->id,
                'type' => $incident->type->value,
                'type_label' => $incident->type->label(),
                'severity' => $incident->severity->value,
                'severity_label' => $incident->severity->label(),
                'status' => $incident->status->value,
                'status_label' => $incident->status->label(),
                'description' => $incident->description,
                'resolution' => $incident->resolution,
                'reported_by' => $incident->reportedBy?->name,
                'resolved_by' => $incident->resolvedBy?->name,
                'created_at' => $incident->created_at?->toIso8601String(),
                'resolved_at' => $incident->resolved_at?->toIso8601String(),
                'can_resolve' => in_array($incident->status, [
                    IncidentStatus::Open,
                    IncidentStatus::UnderReview,
                ], true),
            ],
            'order' => $incident->order ? OrderData::transform($incident->order) : null,
            'financial' => $incident->order ? OrderData::financialDetail($incident->order) : null,
            'responsibilityOptions' => collect(CancellationResponsibility::cases())
                ->reject(fn (CancellationResponsibility $item): bool => $item === CancellationResponsibility::UnderReview)
                ->map(fn (CancellationResponsibility $item): array => [
                    'value' => $item->value,
                    'label' => $item->label(),
                ])->values()->all(),
        ]);
    }

    public function resolve(
        ResolveIncidentRequest $request,
        Incident $incident,
        IncidentService $incidents,
    ): RedirectResponse {
        $incidents->resolve($incident, $request->user(), $request->string('resolution')->toString());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Incidencia resuelta.',
        ]);

        return back();
    }

    public function reviewCancellation(
        ReviewCancellationRequest $request,
        OrderCancellation $cancellation,
        OrderCancellationService $cancellations,
    ): RedirectResponse {
        $cancellations->review(
            $cancellation,
            $request->user(),
            CancellationResponsibility::from($request->string('responsibility')->toString()),
            $request->input('review_notes'),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Responsabilidad actualizada.',
        ]);

        return back();
    }

    public function cancelOrder(
        CancelOrderRequest $request,
        Order $order,
        OrderCancellationService $cancellations,
    ): RedirectResponse {
        $responsibility = filled($request->input('responsibility'))
            ? CancellationResponsibility::from($request->string('responsibility')->toString())
            : null;

        $cancellations->cancelByAdmin(
            $order,
            $request->user(),
            CancellationReasonCode::from($request->string('reason_code')->toString()),
            $request->input('reason'),
            $responsibility,
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Pedido cancelado.',
        ]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function listRow(Incident $incident): array
    {
        return [
            'id' => $incident->id,
            'order_number' => $incident->order?->order_number,
            'type_label' => $incident->type->label(),
            'reported_by' => $incident->reportedBy?->name,
            'severity' => $incident->severity->value,
            'severity_label' => $incident->severity->label(),
            'status' => $incident->status->value,
            'status_label' => $incident->status->label(),
            'business_name' => $incident->business?->name ?? $incident->order?->branch?->business?->name,
            'created_at' => $incident->created_at?->toIso8601String(),
        ];
    }
}
