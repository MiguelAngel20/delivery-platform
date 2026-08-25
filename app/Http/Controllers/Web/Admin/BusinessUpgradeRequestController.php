<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\UpgradeRequestStatus;
use App\Enums\UpgradeRequestType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReviewBusinessUpgradeRequest;
use App\Models\Business;
use App\Models\BusinessUpgradeRequest;
use App\Services\BusinessLimitService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class BusinessUpgradeRequestController extends Controller
{
    public function __construct(
        private readonly BusinessLimitService $limitService,
    ) {}

    public function approve(
        ReviewBusinessUpgradeRequest $request,
        Business $business,
        BusinessUpgradeRequest $upgradeRequest,
    ): RedirectResponse {
        $this->ensureSameBusiness($business, $upgradeRequest);
        $this->authorize('review', $upgradeRequest);

        if ($upgradeRequest->status !== UpgradeRequestStatus::Pending) {
            throw ValidationException::withMessages([
                'status' => 'Solo se pueden aprobar solicitudes pendientes.',
            ]);
        }

        $data = $request->validated();

        DB::transaction(function () use ($business, $upgradeRequest, $data, $request): void {
            /** @var BusinessUpgradeRequest $lockedRequest */
            $lockedRequest = BusinessUpgradeRequest::query()
                ->whereKey($upgradeRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedRequest->status !== UpgradeRequestStatus::Pending) {
                throw ValidationException::withMessages([
                    'status' => 'Solo se pueden aprobar solicitudes pendientes.',
                ]);
            }

            $limits = $this->limitService->lockForUpdate($business);

            if (($data['apply_limit_increase'] ?? false) === true) {
                $quantity = max(1, (int) ($data['quantity'] ?? $lockedRequest->requested_quantity));

                if ($lockedRequest->type === UpgradeRequestType::AdditionalBranch) {
                    $limits->update([
                        'max_branches' => $limits->max_branches + $quantity,
                    ]);
                }

                if ($lockedRequest->type === UpgradeRequestType::AdditionalEmployees) {
                    $limits->update([
                        'max_employees_per_branch' => $limits->max_employees_per_branch + $quantity,
                    ]);
                }
            }

            $updated = BusinessUpgradeRequest::query()
                ->whereKey($lockedRequest->id)
                ->where('status', UpgradeRequestStatus::Pending)
                ->update([
                    'status' => UpgradeRequestStatus::Approved,
                    'notes' => $data['notes'] ?? $lockedRequest->notes,
                    'reviewed_by_user_id' => $request->user()?->id,
                    'reviewed_at' => now(),
                ]);

            if ($updated !== 1) {
                throw ValidationException::withMessages([
                    'status' => 'Solo se pueden aprobar solicitudes pendientes.',
                ]);
            }
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Solicitud aprobada.',
        ]);

        return back();
    }

    public function reject(
        ReviewBusinessUpgradeRequest $request,
        Business $business,
        BusinessUpgradeRequest $upgradeRequest,
    ): RedirectResponse {
        $this->ensureSameBusiness($business, $upgradeRequest);
        $this->authorize('review', $upgradeRequest);

        if ($upgradeRequest->status !== UpgradeRequestStatus::Pending) {
            throw ValidationException::withMessages([
                'status' => 'Solo se pueden rechazar solicitudes pendientes.',
            ]);
        }

        DB::transaction(function () use ($upgradeRequest, $request): void {
            /** @var BusinessUpgradeRequest $lockedRequest */
            $lockedRequest = BusinessUpgradeRequest::query()
                ->whereKey($upgradeRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            $updated = BusinessUpgradeRequest::query()
                ->whereKey($lockedRequest->id)
                ->where('status', UpgradeRequestStatus::Pending)
                ->update([
                    'status' => UpgradeRequestStatus::Rejected,
                    'notes' => $request->validated('notes') ?? $lockedRequest->notes,
                    'reviewed_by_user_id' => $request->user()?->id,
                    'reviewed_at' => now(),
                ]);

            if ($updated !== 1) {
                throw ValidationException::withMessages([
                    'status' => 'Solo se pueden rechazar solicitudes pendientes.',
                ]);
            }
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Solicitud rechazada.',
        ]);

        return back();
    }

    private function ensureSameBusiness(Business $business, BusinessUpgradeRequest $upgradeRequest): void
    {
        abort_unless($upgradeRequest->business_id === $business->id, 404);
    }
}
