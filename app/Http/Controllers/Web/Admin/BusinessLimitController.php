<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateBusinessLimitsRequest;
use App\Models\Business;
use App\Services\BusinessLimitService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class BusinessLimitController extends Controller
{
    public function __construct(
        private readonly BusinessLimitService $limitService,
    ) {}

    public function update(UpdateBusinessLimitsRequest $request, Business $business): RedirectResponse
    {
        $this->authorize('update', $business);

        $limits = $this->limitService->ensureLimits($business);
        $limits->update($request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Límites actualizados correctamente.',
        ]);

        return back();
    }
}
