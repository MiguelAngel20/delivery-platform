<?php

namespace App\Http\Controllers\Web\Admin;

use App\Actions\Businesses\ActivateBusiness;
use App\Actions\Businesses\ApproveBusiness;
use App\Actions\Businesses\RejectBusiness;
use App\Actions\Businesses\SuspendBusiness;
use App\Enums\BusinessDeliveryMode;
use App\Enums\BusinessOperationMode;
use App\Enums\BusinessStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IndexBusinessRequest;
use App\Http\Requests\Admin\RejectBusinessRequest;
use App\Http\Requests\Admin\StoreBusinessRequest;
use App\Http\Requests\Admin\SuspendBusinessRequest;
use App\Http\Requests\Admin\UpdateBusinessRequest;
use App\Models\Business;
use App\Services\BusinessLimitService;
use App\Services\Notifications\RideNotificationDispatcher;
use App\Support\BusinessBannerStorage;
use App\Support\BusinessDriverData;
use App\Support\BusinessHours;
use App\Support\BusinessLogoStorage;
use App\Support\BusinessMembershipData;
use App\Support\BusinessTypes;
use App\Support\UniqueSlug;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class BusinessController extends Controller
{
    public function __construct(
        private readonly BusinessLogoStorage $logoStorage,
        private readonly BusinessBannerStorage $bannerStorage,
        private readonly BusinessLimitService $limitService,
        private readonly RideNotificationDispatcher $notifications,
    ) {}

    public function index(IndexBusinessRequest $request): Response
    {
        $filters = $request->validated();

        $businesses = Business::query()
            ->select([
                'id',
                'name',
                'slug',
                'operation_mode',
                'delivery_mode',
                'status',
                'email',
                'phone',
                'created_at',
            ])
            ->withCount('branches')
            ->when(
                filled($filters['search'] ?? null),
                function ($query) use ($filters): void {
                    $search = $filters['search'];
                    $query->where(function ($inner) use ($search): void {
                        $inner->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    });
                },
            )
            ->when(
                filled($filters['status'] ?? null),
                fn ($query) => $query->where('status', $filters['status']),
            )
            ->when(
                filled($filters['operation_mode'] ?? null),
                fn ($query) => $query->where('operation_mode', $filters['operation_mode']),
            )
            ->when(
                filled($filters['delivery_mode'] ?? null),
                fn ($query) => $query->where('delivery_mode', $filters['delivery_mode']),
            )
            ->latest()
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Business $business): array => [
                'id' => $business->id,
                'name' => $business->name,
                'slug' => $business->slug,
                'operation_mode' => $business->operation_mode->value,
                'operation_mode_label' => $business->operation_mode->label(),
                'delivery_mode' => $business->delivery_mode->value,
                'delivery_mode_label' => $business->delivery_mode->label(),
                'status' => $business->status->value,
                'status_label' => $business->status->label(),
                'branches_count' => $business->branches_count,
                'created_at' => $business->created_at?->toDateString(),
            ]);

        return Inertia::render('admin/businesses/index', [
            'businesses' => $businesses,
            'filters' => [
                'search' => $filters['search'] ?? '',
                'status' => $filters['status'] ?? '',
                'operation_mode' => $filters['operation_mode'] ?? '',
                'delivery_mode' => $filters['delivery_mode'] ?? '',
            ],
            'options' => $this->filterOptions(),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Business::class);

        return Inertia::render('admin/businesses/create', [
            'options' => $this->formOptions(),
        ]);
    }

    public function store(StoreBusinessRequest $request): RedirectResponse
    {
        $data = collect($request->validated())->except(['logo', 'banner'])->all();
        $status = $data['status'] instanceof BusinessStatus
            ? $data['status']
            : BusinessStatus::from((string) $data['status']);

        $business = DB::transaction(function () use ($request, $data, $status) {
            $business = Business::query()->create([
                ...$data,
                'status' => $status,
                'slug' => UniqueSlug::forBusiness($data['name']),
                'created_by_user_id' => $request->user()?->id,
                'approved_by_user_id' => $status === BusinessStatus::Active
                    ? $request->user()?->id
                    : null,
                'approved_at' => $status === BusinessStatus::Active
                    ? now()
                    : null,
            ]);

            $this->limitService->ensureLimits($business);

            if ($request->hasFile('logo')) {
                $this->logoStorage->replace($business, $request->file('logo'));
            }

            if ($request->hasFile('banner')) {
                $this->bannerStorage->replace($business, $request->file('banner'));
            }

            return $business;
        });

        if ($status === BusinessStatus::PendingApproval) {
            $this->notifications->businessPendingApproval($business);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Empresa creada correctamente.',
        ]);

        return to_route('admin.businesses.show', $business);
    }

    public function show(Business $business): Response
    {
        $this->authorize('view', $business);

        $business->load([
            'branches' => fn ($query) => $query->orderBy('name'),
            'memberships.user:id,first_name,last_name,name,email,phone,role',
            'memberships.branches:id,name',
            'drivers.user:id,first_name,last_name,name,email,phone',
            'drivers.branches:id,name,business_id',
            'upgradeRequests' => fn ($query) => $query->latest()->limit(20),
            'upgradeRequests.requestedBy:id,name',
            'upgradeRequests.branch:id,name',
            'approvedBy:id,name',
            'createdBy:id,name',
            'limits',
        ]);

        return Inertia::render('admin/businesses/show', [
            'business' => $this->transformBusiness($business),
            'limits' => $this->limitService->summary($business),
            'upgradeRequests' => $business->upgradeRequests->map(fn ($item): array => [
                'id' => $item->id,
                'type' => $item->type->value,
                'type_label' => $item->type->label(),
                'requested_quantity' => $item->requested_quantity,
                'status' => $item->status->value,
                'status_label' => $item->status->label(),
                'notes' => $item->notes,
                'branch' => $item->branch?->only(['id', 'name']),
                'requested_by' => $item->requestedBy?->only(['id', 'name']),
                'created_at' => $item->created_at?->toDateTimeString(),
            ])->values()->all(),
            'options' => $this->formOptions(),
        ]);
    }

    public function edit(Business $business): Response
    {
        $this->authorize('update', $business);

        return Inertia::render('admin/businesses/edit', [
            'business' => $this->transformBusiness($business),
            'options' => $this->formOptions(),
        ]);
    }

    public function update(UpdateBusinessRequest $request, Business $business): RedirectResponse
    {
        $data = collect($request->validated())->except(['logo', 'banner'])->all();
        $nameChanged = $business->name !== $data['name'];

        $business->update([
            ...$data,
            'slug' => $nameChanged
                ? UniqueSlug::forBusiness($data['name'], $business->id)
                : $business->slug,
        ]);

        if ($request->hasFile('logo')) {
            $this->logoStorage->replace($business, $request->file('logo'));
        }

        if ($request->hasFile('banner')) {
            $this->bannerStorage->replace($business, $request->file('banner'));
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Empresa actualizada correctamente.',
        ]);

        return to_route('admin.businesses.show', $business);
    }

    public function approve(Request $request, Business $business, ApproveBusiness $action): RedirectResponse
    {
        $this->authorize('approve', $business);

        $action->handle($business, $request->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Empresa aprobada.',
        ]);

        return back();
    }

    public function reject(RejectBusinessRequest $request, Business $business, RejectBusiness $action): RedirectResponse
    {
        $action->handle($business, $request->validated('reason'));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Empresa rechazada.',
        ]);

        return back();
    }

    public function suspend(SuspendBusinessRequest $request, Business $business, SuspendBusiness $action): RedirectResponse
    {
        $action->handle($business, $request->validated('reason'));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Empresa suspendida.',
        ]);

        return back();
    }

    public function activate(Business $business, ActivateBusiness $action): RedirectResponse
    {
        $this->authorize('activate', $business);

        $action->handle($business);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Empresa reactivada.',
        ]);

        return back();
    }

    /**
     * @return array{
     *     operation_modes: list<array{value: string, label: string}>,
     *     delivery_modes: list<array{value: string, label: string}>,
     *     statuses: list<array{value: string, label: string}>
     * }
     */
    private function filterOptions(): array
    {
        return [
            'operation_modes' => collect(BusinessOperationMode::cases())
                ->map(fn (BusinessOperationMode $mode): array => [
                    'value' => $mode->value,
                    'label' => $mode->label(),
                ])
                ->values()
                ->all(),
            'delivery_modes' => collect(BusinessDeliveryMode::cases())
                ->map(fn (BusinessDeliveryMode $mode): array => [
                    'value' => $mode->value,
                    'label' => $mode->label(),
                ])
                ->values()
                ->all(),
            'statuses' => collect(BusinessStatus::cases())
                ->map(fn (BusinessStatus $status): array => [
                    'value' => $status->value,
                    'label' => $status->label(),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            ...$this->filterOptions(),
            'business_types' => BusinessTypes::options(),
            'weekdays' => BusinessHours::dayOptions(),
            'default_opening_hours' => BusinessHours::defaults(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function transformBusiness(Business $business): array
    {
        return [
            'id' => $business->id,
            'name' => $business->name,
            'slug' => $business->slug,
            'description' => $business->description,
            'business_type' => $business->business_type,
            'operation_mode' => $business->operation_mode->value,
            'operation_mode_label' => $business->operation_mode->label(),
            'delivery_mode' => $business->delivery_mode->value,
            'delivery_mode_label' => $business->delivery_mode->label(),
            'status' => $business->status->value,
            'status_label' => $business->status->label(),
            'phone' => $business->phone,
            'email' => $business->email,
            'logo_path' => $business->logo_path,
            'logo_url' => $this->logoStorage->url($business->logo_path),
            'banner_path' => $business->banner_path,
            'banner_url' => $this->bannerStorage->url($business->banner_path),
            'rejection_reason' => $business->rejection_reason,
            'suspension_reason' => $business->suspension_reason,
            'approved_at' => $business->approved_at?->toDateTimeString(),
            'created_at' => $business->created_at?->toDateTimeString(),
            'created_by' => $business->createdBy?->only(['id', 'name']),
            'approved_by' => $business->approvedBy?->only(['id', 'name']),
            'branches' => $business->relationLoaded('branches')
                ? $business->branches->map(fn ($branch): array => [
                    'id' => $branch->id,
                    'name' => $branch->name,
                    'phone' => $branch->phone,
                    'address_text' => $branch->address_text,
                    'reference' => $branch->reference,
                    'latitude' => $branch->latitude,
                    'longitude' => $branch->longitude,
                    'google_maps_url' => $branch->google_maps_url,
                    'opening_hours' => BusinessHours::present($branch->opening_hours),
                    'schedule_label' => BusinessHours::todayLabel($branch->opening_hours),
                    'status' => $branch->status->value,
                    'status_label' => $branch->status->label(),
                ])->values()->all()
                : [],
            'memberships' => $business->relationLoaded('memberships')
                ? $business->memberships->map(fn ($membership): array => BusinessMembershipData::transform($membership))->values()->all()
                : [],
            'drivers' => $business->relationLoaded('drivers')
                ? $business->drivers->map(fn ($driver): array => BusinessDriverData::transform($driver, $business))->values()->all()
                : [],
        ];
    }
}
