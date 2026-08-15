<?php

namespace App\Http\Controllers\Web\Driver;

use App\Enums\DriverAvailabilityStatus;
use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\User;
use App\Services\Drivers\DriverLocationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class AvailabilityController extends Controller
{
    public function update(Request $request, DriverLocationService $locations): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        /** @var Driver|null $driver */
        $driver = $user->driver;

        abort_unless($driver !== null, 403);

        $validated = $request->validate([
            'availability_status' => ['required', Rule::enum(DriverAvailabilityStatus::class)],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'accuracy_meters' => ['nullable', 'integer', 'min:0', 'max:5000'],
        ]);

        $status = DriverAvailabilityStatus::from($validated['availability_status']);

        $driver->forceFill([
            'availability_status' => $status,
        ])->save();

        if ($status === DriverAvailabilityStatus::Offline) {
            $locations->clear($driver);
        } elseif (
            isset($validated['latitude'], $validated['longitude'])
            && is_numeric($validated['latitude'])
            && is_numeric($validated['longitude'])
        ) {
            $locations->update(
                $driver,
                (float) $validated['latitude'],
                (float) $validated['longitude'],
                isset($validated['accuracy_meters']) ? (int) $validated['accuracy_meters'] : null,
            );
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Disponibilidad actualizada.',
        ]);

        return back();
    }
}
