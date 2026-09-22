<?php

namespace App\Http\Controllers\Web\Admin;

use App\Actions\Drivers\CreatePlatformDriver;
use App\Actions\Drivers\DeletePlatformDriver;
use App\Actions\Drivers\UpdatePlatformDriver;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePlatformDriverRequest;
use App\Http\Requests\Admin\UpdateDriverCommissionRequest;
use App\Http\Requests\Admin\UpdatePlatformDriverRequest;
use App\Models\Driver;
use App\Services\Drivers\DriverCommissionService;
use App\Support\ReputationPresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DriverController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->hasRole(UserRole::SystemAdmin), 403);

        $drivers = Driver::query()
            ->with(['user', 'metrics'])
            ->when(
                filled($request->input('search')),
                function (Builder $query) use ($request): void {
                    $search = $request->string('search')->toString();
                    $query->whereHas('user', function (Builder $user) use ($search): void {
                        $user->where('name', 'like', "%{$search}%")
                            ->orWhere('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    });
                },
            )
            ->when(
                filled($request->input('requires_review')),
                fn (Builder $query) => $query->whereHas(
                    'metrics',
                    fn (Builder $metrics) => $metrics->where(
                        'trust_score',
                        '<=',
                        (float) config('reputation.driver.requires_review_max_score', 40),
                    ),
                ),
            )
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(function (Driver $driver): array {
                $row = ReputationPresenter::driverForAdmin($driver);
                $row['commission_owed'] = app(DriverCommissionService::class)->unpaidAmount($driver);

                return $row;
            });

        return Inertia::render('admin/drivers/index', [
            'drivers' => $drivers,
            'filters' => [
                'search' => $request->input('search', ''),
                'requires_review' => $request->boolean('requires_review') ? '1' : '',
            ],
        ]);
    }

    public function store(
        StorePlatformDriverRequest $request,
        CreatePlatformDriver $create,
    ): RedirectResponse {
        $create->handle($request->validated(), $request->user());

        return back()->with('success', 'Repartidor creado. Se envió un correo para verificar su cuenta.');
    }

    public function update(
        UpdatePlatformDriverRequest $request,
        Driver $driver,
        UpdatePlatformDriver $update,
    ): RedirectResponse {
        $update->handle($driver, $request->validated());

        return back()->with('success', 'Repartidor actualizado.');
    }

    public function destroy(
        Request $request,
        Driver $driver,
        DeletePlatformDriver $delete,
    ): RedirectResponse {
        abort_unless($request->user()?->hasRole(UserRole::SystemAdmin), 403);

        $delete->handle($driver);

        return back()->with('success', 'Repartidor eliminado.');
    }

    public function show(Driver $driver, DriverCommissionService $commissions): Response
    {
        abort_unless(request()->user()?->hasRole(UserRole::SystemAdmin), 403);
        $driver->loadMissing(['user', 'metrics']);

        $payload = ReputationPresenter::driverForAdmin($driver);
        $payload['commission_owed'] = $commissions->unpaidAmount($driver);
        $payload['commission_blocked'] = $commissions->isBlockedFromAccepting($driver);

        return Inertia::render('admin/drivers/show', [
            'driver' => $payload,
        ]);
    }

    public function updateCommission(
        UpdateDriverCommissionRequest $request,
        Driver $driver,
        DriverCommissionService $commissions,
    ): RedirectResponse {
        $commissions->updateSettings($driver, $request->validated());

        return back()->with('success', 'Comisión del repartidor actualizada.');
    }

    public function markCommissionPaid(
        Request $request,
        Driver $driver,
        DriverCommissionService $commissions,
    ): RedirectResponse {
        abort_unless($request->user()?->hasRole(UserRole::SystemAdmin), 403);

        $updated = $commissions->markOutstandingPaid($driver, $request->user());

        return back()->with(
            'success',
            $updated > 0
                ? 'Pago de comisión verificado.'
                : 'No había comisión pendiente por verificar.',
        );
    }
}
