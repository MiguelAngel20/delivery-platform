<?php

namespace App\Http\Controllers\Web\Driver;

use App\Enums\DriverAvailabilityStatus;
use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\User;
use App\Services\Dispatch\DriverActiveOrderService;
use App\Services\Drivers\DriverLocationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class AvailabilityController extends Controller
{
    public function update(
        Request $request,
        DriverLocationService $locations,
        DriverActiveOrderService $activeOrders,
    ): RedirectResponse {
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

        DB::transaction(function () use ($driver, $status, $activeOrders): void {
            /** @var Driver $locked */
            $locked = Driver::query()
                ->whereKey($driver->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (
                $status === DriverAvailabilityStatus::Available
                && $activeOrders->activeCount($locked) > 0
            ) {
                throw ValidationException::withMessages([
                    'availability_status' => 'No puedes marcar Available mientras tienes pedidos activos.',
                ]);
            }

            $locked->forceFill([
                'availability_status' => $status,
            ])->save();
        });

        $driver = $driver->fresh();

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
