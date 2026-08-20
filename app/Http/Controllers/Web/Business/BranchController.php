<?php

namespace App\Http\Controllers\Web\Business;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Business\Concerns\ResolvesBusinessCatalog;
use App\Http\Requests\Business\UpdateBusinessBranchRequest;
use App\Models\Business;
use App\Models\BusinessBranch;
use App\Support\BusinessAccess;
use App\Support\BusinessBranchData;
use App\Support\BusinessHours;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BranchController extends Controller
{
    use ResolvesBusinessCatalog;

    public function __construct(
        private readonly BusinessAccess $businessAccess,
    ) {}

    public function index(Request $request): Response
    {
        $business = $this->currentBusiness($request);
        $branches = $this->businessAccess
            ->accessibleBranches($request->user(), $business)
            ->map(fn (BusinessBranch $branch): array => BusinessBranchData::transform($branch))
            ->values()
            ->all();

        return Inertia::render('business/settings/branches/index', [
            'branches' => $branches,
        ]);
    }

    public function edit(Request $request, BusinessBranch $branch): Response
    {
        $business = $this->currentBusiness($request);
        $this->ensureBranchBelongsToBusiness($business, $branch);
        $this->authorize('update', $branch);

        return Inertia::render('business/settings/branches/edit', [
            'branch' => BusinessBranchData::transform($branch),
            'options' => [
                'weekdays' => BusinessHours::dayOptions(),
                'default_opening_hours' => BusinessHours::defaults(),
            ],
        ]);
    }

    public function update(UpdateBusinessBranchRequest $request, BusinessBranch $branch): RedirectResponse
    {
        $business = $this->currentBusiness($request);
        $this->ensureBranchBelongsToBusiness($business, $branch);

        $data = $request->validated();
        $data['opening_hours'] = BusinessHours::normalize($data['opening_hours'] ?? null);
        $branch->update($data);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Sucursal actualizada correctamente.',
        ]);

        return to_route('business.settings.branches.edit', $branch);
    }

    private function ensureBranchBelongsToBusiness(Business $business, BusinessBranch $branch): void
    {
        abort_unless($branch->business_id === $business->id, 404);
    }
}
