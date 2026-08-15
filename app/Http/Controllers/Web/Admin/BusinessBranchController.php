<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\BranchStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreBusinessBranchRequest;
use App\Http\Requests\Admin\UpdateBusinessBranchRequest;
use App\Models\Business;
use App\Models\BusinessBranch;
use App\Services\BusinessLimitService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class BusinessBranchController extends Controller
{
    public function __construct(
        private readonly BusinessLimitService $limitService,
    ) {}

    public function store(StoreBusinessBranchRequest $request, Business $business): RedirectResponse
    {
        $this->authorize('create', [BusinessBranch::class, $business]);

        DB::transaction(function () use ($request, $business): void {
            $this->limitService->assertCanCreateBranch($business);
            $business->branches()->create($request->validated());
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Sucursal creada correctamente.',
        ]);

        return back();
    }

    public function update(
        UpdateBusinessBranchRequest $request,
        Business $business,
        BusinessBranch $branch,
    ): RedirectResponse {
        $this->ensureBranchBelongsToBusiness($business, $branch);

        $branch->update($request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Sucursal actualizada correctamente.',
        ]);

        return back();
    }

    public function deactivate(Business $business, BusinessBranch $branch): RedirectResponse
    {
        $this->ensureBranchBelongsToBusiness($business, $branch);
        $this->authorize('deactivate', $branch);

        if ($branch->status === BranchStatus::Inactive) {
            throw ValidationException::withMessages([
                'status' => 'La sucursal ya está inactiva.',
            ]);
        }

        $branch->update(['status' => BranchStatus::Inactive]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Sucursal desactivada.',
        ]);

        return back();
    }

    public function activate(Business $business, BusinessBranch $branch): RedirectResponse
    {
        $this->ensureBranchBelongsToBusiness($business, $branch);
        $this->authorize('activate', $branch);

        $branch->update(['status' => BranchStatus::Active]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Sucursal reactivada.',
        ]);

        return back();
    }

    private function ensureBranchBelongsToBusiness(Business $business, BusinessBranch $branch): void
    {
        if ($branch->business_id !== $business->id) {
            abort(404);
        }
    }
}
