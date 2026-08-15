<?php

namespace App\Http\Controllers\Web\Driver;

use App\Http\Controllers\Controller;
use App\Support\ReputationPresenter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $driver = $request->user()?->driver;
        abort_unless($driver !== null, 403);

        $driver->loadMissing(['user', 'metrics']);

        return Inertia::render('driver/profile/index', [
            'reputation' => ReputationPresenter::driverForSelf($driver),
            'phone' => $driver->user?->phone,
            'scope_label' => $driver->driver_scope->label(),
            'approval_status_label' => $driver->approval_status->label(),
        ]);
    }
}
