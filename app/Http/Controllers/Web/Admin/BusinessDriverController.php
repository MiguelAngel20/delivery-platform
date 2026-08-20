<?php

namespace App\Http\Controllers\Web\Admin;

use App\Actions\Businesses\CreateBusinessDriver;
use App\Actions\Businesses\UpdateBusinessDriver;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreBusinessDriverRequest;
use App\Http\Requests\Admin\UpdateBusinessDriverRequest;
use App\Models\Business;
use App\Models\Driver;
use App\Support\BusinessDriverData;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class BusinessDriverController extends Controller
{
    public function index(Business $business): Response
    {
        $this->authorize('view', $business);

        $business->load([
            'drivers' => fn ($query) => $query
                ->with([
                    'user:id,first_name,last_name,name,email,phone',
                    'branches:id,name,business_id',
                ])
                ->latest(),
        ]);

        return Inertia::render('admin/businesses/drivers/index', [
            'business' => [
                'id' => $business->id,
                'name' => $business->name,
                'delivery_mode' => $business->delivery_mode->value,
            ],
            'drivers' => $business->drivers
                ->map(fn (Driver $driver): array => BusinessDriverData::transform($driver, $business))
                ->values()
                ->all(),
            'options' => BusinessDriverData::formOptions($business),
        ]);
    }

    public function create(Business $business): Response
    {
        $this->authorize('update', $business);

        return Inertia::render('admin/businesses/drivers/create', [
            'business' => [
                'id' => $business->id,
                'name' => $business->name,
            ],
            'options' => BusinessDriverData::formOptions($business),
        ]);
    }

    public function store(
        StoreBusinessDriverRequest $request,
        Business $business,
        CreateBusinessDriver $action,
    ): RedirectResponse {
        $driver = $action->handle($business, $request->validated(), $request->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => sprintf(
                'Repartidor creado. Entra con el correo y la contraseña %s. Deberá cambiarla al iniciar sesión.',
                config('business.users.temporary_password'),
            ),
        ]);

        return to_route('admin.businesses.drivers.edit', [$business, $driver]);
    }

    public function edit(Business $business, Driver $driver): Response
    {
        $this->ensureBelongsToBusiness($business, $driver);
        $this->authorize('update', $business);

        $driver->load(['user', 'branches:id,name,business_id']);

        return Inertia::render('admin/businesses/drivers/edit', [
            'business' => [
                'id' => $business->id,
                'name' => $business->name,
            ],
            'driver' => BusinessDriverData::transform($driver, $business),
            'options' => BusinessDriverData::formOptions($business),
        ]);
    }

    public function update(
        UpdateBusinessDriverRequest $request,
        Business $business,
        Driver $driver,
        UpdateBusinessDriver $action,
    ): RedirectResponse {
        $this->ensureBelongsToBusiness($business, $driver);

        $action->handle($business, $driver, $request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Repartidor actualizado.',
        ]);

        return to_route('admin.businesses.drivers.edit', [$business, $driver]);
    }

    public function destroy(Business $business, Driver $driver): RedirectResponse
    {
        $this->ensureBelongsToBusiness($business, $driver);
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

        return to_route('admin.businesses.drivers.index', $business);
    }

    private function ensureBelongsToBusiness(Business $business, Driver $driver): void
    {
        abort_unless($driver->businesses()->whereKey($business->id)->exists(), 404);
    }
}
