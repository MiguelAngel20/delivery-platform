<?php

namespace App\Http\Controllers\Web\Driver;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\User;
use App\Services\Drivers\DriverLocationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    public function update(Request $request, DriverLocationService $locations): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        /** @var Driver|null $driver */
        $driver = $user->driver;

        abort_unless($driver !== null, 403);

        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy_meters' => ['nullable', 'integer', 'min:0', 'max:5000'],
        ]);

        $locations->update(
            $driver,
            (float) $validated['latitude'],
            (float) $validated['longitude'],
            isset($validated['accuracy_meters']) ? (int) $validated['accuracy_meters'] : null,
        );

        return back();
    }
}
