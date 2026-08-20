<?php

namespace App\Http\Controllers\Web\Business;

use App\Enums\UpgradeRequestStatus;
use App\Enums\UpgradeRequestType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Business\StoreUpgradeRequest;
use App\Models\Business;
use App\Models\BusinessUpgradeRequest;
use App\Models\User;
use App\Services\Notifications\RideNotificationDispatcher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UpgradeRequestController extends Controller
{
    public function __construct(
        private readonly RideNotificationDispatcher $notifications,
    ) {}

    public function index(Request $request): Response
    {
        $business = $this->currentBusiness($request);
        $this->authorize('viewAny', [BusinessUpgradeRequest::class, $business]);

        $requests = $business->upgradeRequests()
            ->with(['branch:id,name', 'requestedBy:id,name'])
            ->latest()
            ->get()
            ->map(fn (BusinessUpgradeRequest $item): array => [
                'id' => $item->id,
                'type' => $item->type->value,
                'type_label' => $item->type->label(),
                'requested_quantity' => $item->requested_quantity,
                'status' => $item->status->value,
                'status_label' => $item->status->label(),
                'notes' => $item->notes,
                'branch' => $item->branch?->only(['id', 'name']),
                'created_at' => $item->created_at?->toDateTimeString(),
            ]);

        return Inertia::render('business/upgrade-requests/index', [
            'business' => [
                'id' => $business->id,
                'name' => $business->name,
            ],
            'requests' => $requests,
            'options' => [
                'types' => collect(UpgradeRequestType::cases())
                    ->map(fn (UpgradeRequestType $type): array => [
                        'value' => $type->value,
                        'label' => $type->label(),
                    ])
                    ->values()
                    ->all(),
                'branches' => $business->branches()
                    ->orderBy('name')
                    ->get(['id', 'name'])
                    ->map(fn ($branch): array => [
                        'id' => $branch->id,
                        'name' => $branch->name,
                    ])
                    ->values()
                    ->all(),
            ],
        ]);
    }

    public function store(StoreUpgradeRequest $request): RedirectResponse
    {
        $business = $this->currentBusiness($request);

        $data = $request->validated();
        $type = UpgradeRequestType::from((string) $data['type']);

        $upgradeRequest = BusinessUpgradeRequest::query()->create([
            'business_id' => $business->id,
            'requested_by_user_id' => $request->user()?->id,
            'type' => $type,
            'requested_quantity' => (int) $data['requested_quantity'],
            'branch_id' => $type === UpgradeRequestType::AdditionalEmployees
                ? ($data['branch_id'] ?? null)
                : null,
            'status' => UpgradeRequestStatus::Pending,
            'notes' => $data['notes'] ?? null,
        ]);

        $this->notifications->upgradeRequested($upgradeRequest);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Solicitud enviada correctamente.',
        ]);

        return back();
    }

    private function currentBusiness(Request $request): Business
    {
        /** @var User $user */
        $user = $request->user();
        $membership = $user->activeBusinessMembership();

        abort_unless(
            $membership !== null && $membership->isAdmin() && $membership->business !== null,
            403,
        );

        return $membership->business;
    }
}
