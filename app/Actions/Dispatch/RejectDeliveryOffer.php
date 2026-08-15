<?php

namespace App\Actions\Dispatch;

use App\Enums\BusinessDeliveryMode;
use App\Enums\DriverAssignmentStatus;
use App\Models\Driver;
use App\Models\DriverAssignment;
use App\Models\Order;
use App\Services\Dispatch\DriverEligibilityService;
use App\Support\OrderActiveStatuses;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RejectDeliveryOffer
{
    public function __construct(
        private readonly DriverEligibilityService $eligibility,
    ) {}

    public function handle(Order $order, Driver $driver): DriverAssignment
    {
        return DB::transaction(function () use ($order, $driver): DriverAssignment {
            /** @var Order $locked */
            $locked = Order::query()
                ->with(['branch.business'])
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            $driver->loadMissing('businesses');

            if ($locked->assigned_driver_id !== null) {
                throw ValidationException::withMessages([
                    'order' => 'Este pedido ya fue tomado por otro repartidor.',
                ]);
            }

            if (! in_array($locked->order_status, OrderActiveStatuses::offerable(), true)) {
                throw ValidationException::withMessages([
                    'order' => 'El pedido no está disponible.',
                ]);
            }

            $business = $locked->branch?->business;

            if ($business === null || $locked->branch === null) {
                throw ValidationException::withMessages([
                    'order' => 'Negocio inválido.',
                ]);
            }

            if ($business->delivery_mode === BusinessDeliveryMode::None) {
                throw ValidationException::withMessages([
                    'order' => 'Este establecimiento no usa repartidores.',
                ]);
            }

            if (! $this->eligibility->matchesDeliveryMode(
                $driver,
                $business->delivery_mode,
                $business->id,
            )) {
                throw ValidationException::withMessages([
                    'order' => 'No puedes rechazar un pedido no elegible.',
                ]);
            }

            $alreadyTerminal = DriverAssignment::query()
                ->where('order_id', $locked->id)
                ->where('driver_id', $driver->id)
                ->whereIn('status', [
                    DriverAssignmentStatus::Rejected->value,
                    DriverAssignmentStatus::Expired->value,
                    DriverAssignmentStatus::Accepted->value,
                ])
                ->exists();

            if ($alreadyTerminal) {
                throw ValidationException::withMessages([
                    'order' => 'Ya respondiste a este pedido.',
                ]);
            }

            $now = now();

            return DriverAssignment::query()->create([
                'order_id' => $locked->id,
                'driver_id' => $driver->id,
                'status' => DriverAssignmentStatus::Rejected,
                'offered_at' => $now,
                'rejected_at' => $now,
            ]);
        });
    }
}
