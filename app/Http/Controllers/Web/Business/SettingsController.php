<?php

namespace App\Http\Controllers\Web\Business;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Business\Concerns\ResolvesBusinessCatalog;
use App\Services\BusinessLimitService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    use ResolvesBusinessCatalog;

    public function __construct(
        private readonly BusinessLimitService $limitService,
    ) {}

    public function index(Request $request): Response
    {
        $business = $this->currentBusiness($request);

        return Inertia::render('business/settings/index', [
            'business' => [
                'name' => $business->name,
                'delivery_mode' => $business->delivery_mode->value,
                'delivery_mode_label' => $business->delivery_mode->label(),
                'uses_own_drivers' => $business->delivery_mode->usesOwnDrivers(),
            ],
            'limits' => $this->limitService->summary($business),
        ]);
    }
}
