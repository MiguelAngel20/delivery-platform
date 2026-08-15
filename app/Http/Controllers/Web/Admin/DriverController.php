<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Support\ReputationPresenter;
use Illuminate\Database\Eloquent\Builder;
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
            ->through(fn (Driver $driver): array => ReputationPresenter::driverForAdmin($driver));

        return Inertia::render('admin/drivers/index', [
            'drivers' => $drivers,
            'filters' => [
                'search' => $request->input('search', ''),
                'requires_review' => $request->boolean('requires_review') ? '1' : '',
            ],
        ]);
    }

    public function show(Driver $driver): Response
    {
        abort_unless(request()->user()?->hasRole(UserRole::SystemAdmin), 403);
        $driver->loadMissing(['user', 'metrics']);

        return Inertia::render('admin/drivers/show', [
            'driver' => ReputationPresenter::driverForAdmin($driver),
        ]);
    }
}
