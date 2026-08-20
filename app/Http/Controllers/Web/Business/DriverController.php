<?php

namespace App\Http\Controllers\Web\Business;

use App\Actions\Businesses\CreateBusinessDriver;
use App\Actions\Businesses\UpdateBusinessDriver;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Business\Concerns\ResolvesBusinessCatalog;
use App\Http\Requests\Business\StoreBusinessDriverRequest;
use App\Http\Requests\Business\UpdateBusinessDriverRequest;
use App\Models\Business;
use App\Models\Driver;
use App\Support\BusinessAccess;
use App\Support\BusinessDriverData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DriverController extends Controller
{
    use ResolvesBusinessCatalog;

    public function __construct(
        private readonly BusinessAccess $businessAccess,
    ) {}

    public function index(Request $request): Response
    {
        $business = $this->currentBusiness($request);
        $this->authorizeOwnDrivers($business);

        $accessibleBranchIds = $this->businessAccess->accessibleBranchIds($request->user(), $business);

        $business->load([
            'drivers' => fn ($query) => $query
                ->whereHas(
                    'branches',
                    fn ($branchQuery) => $branchQuery->whereIn('business_branches.id', $accessibleBranchIds),
                )
                ->whereDoesntHave(
                    'branches',
                    fn ($branchQuery) => $branchQuery
                        ->where('business_id', $business->id)
                        ->whereNotIn('business_branches.id', $accessibleBranchIds),
                )
                ->with([
                    'user:id,first_name,last_name,name,email,phone',
                    'branches:id,name,business_id',
                ])
                ->latest(),
        ]);

        return Inertia::render('business/drivers/index', [
            'drivers' => $business->drivers
                ->map(fn (Driver $driver): array => BusinessDriverData::transform($driver, $business))
                ->values()
                ->all(),
        ]);
    }

    public function create(Request $request): Response
    {
        $business = $this->currentBusiness($request);
        $this->authorizeOwnDrivers($business);
        $this->authorize('update', $business);

        return Inertia::render('business/drivers/create', [
            'options' => BusinessDriverData::formOptions($business, $request->user()),
        ]);
    }

    public function store(
        StoreBusinessDriverRequest $request,
        CreateBusinessDriver $action,
    ): RedirectResponse {
        $business = $this->currentBusiness($request);

        $driver = $action->handle($business, $request->validated(), $request->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => sprintf(
                'Repartidor creado. Entra con el correo y la contraseña %s. Deberá cambiarla al iniciar sesión.',
                config('business.users.temporary_password'),
            ),
        ]);

        return to_route('business.drivers.edit', $driver);
    }

    public function edit(Request $request, Driver $driver): Response
    {
        $business = $this->currentBusiness($request);
        $this->authorizeOwnDrivers($business);
        $this->ensureDriverInScope($request, $business, $driver);
        $this->authorize('update', $business);

        $driver->load(['user', 'branches:id,name,business_id']);

        return Inertia::render('business/drivers/edit', [
            'driver' => BusinessDriverData::transform($driver, $business),
            'options' => BusinessDriverData::formOptions($business, $request->user()),
        ]);
    }

    public function update(
        UpdateBusinessDriverRequest $request,
        Driver $driver,
        UpdateBusinessDriver $action,
    ): RedirectResponse {
        $business = $this->currentBusiness($request);
        $this->ensureDriverInScope($request, $business, $driver);

        $action->handle($business, $driver, $request->validated(), $request->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Repartidor actualizado.',
        ]);

        return to_route('business.drivers.edit', $driver);
    }

    public function destroy(Request $request, Driver $driver): RedirectResponse
    {
        $business = $this->currentBusiness($request);
        $this->ensureDriverInScope($request, $business, $driver);
        $this->authorize('update', $business);

        $branchIds = $driver->branches()
            ->where('business_id', $business->id)
            ->pluck('business_branches.id')
            ->all();

        if ($branchIds !== []) {
            $driver->branches()->detach($branchIds);
        }

        $business->drivers()->detach($driver->id);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Repartidor desvinculado de la empresa.',
        ]);

        return to_route('business.drivers.index');
    }

    private function authorizeOwnDrivers(Business $business): void
    {
        abort_unless($business->delivery_mode->usesOwnDrivers(), 404);
    }

    private function ensureDriverInScope(Request $request, Business $business, Driver $driver): void
    {
        abort_unless($driver->businesses()->whereKey($business->id)->exists(), 404);

        $accessibleBranchIds = $this->businessAccess->accessibleBranchIds($request->user(), $business);

        abort_unless(
            $driver->branches()
                ->where('business_id', $business->id)
                ->whereIn('business_branches.id', $accessibleBranchIds)
                ->exists(),
            404,
        );

        abort_if(
            $driver->branches()
                ->where('business_id', $business->id)
                ->whereNotIn('business_branches.id', $accessibleBranchIds)
                ->exists(),
            404,
        );
    }
}
