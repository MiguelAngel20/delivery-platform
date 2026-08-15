<?php

namespace App\Services\Dispatch;

use App\Enums\BusinessDeliveryMode;
use App\Enums\DriverApprovalStatus;
use App\Enums\DriverAssignmentStatus;
use App\Enums\DriverAvailabilityStatus;
use App\Enums\DriverScope;
use App\Enums\UserStatus;
use App\Models\Driver;
use App\Models\Order;
use App\Support\OrderActiveStatuses;
use Illuminate\Validation\ValidationException;

final class DriverEligibilityService
{
    public function __construct(
        private readonly DriverActiveOrderService $activeOrders,
    ) {}

    public function isDriverEligibleForOrder(Driver $driver, Order $order): bool
    {
        try {
            $this->assertEligible($driver, $order);

            return true;
        } catch (ValidationException) {
            return false;
        }
    }

    public function assertEligible(Driver $driver, Order $order): void
    {
        $driver->loadMissing(['user', 'businesses']);
        $order->loadMissing(['branch.business']);

        if ($driver->approval_status !== DriverApprovalStatus::Approved) {
            throw ValidationException::withMessages([
                'driver' => 'El repartidor no está aprobado.',
            ]);
        }

        if ($driver->user === null || $driver->user->status !== UserStatus::Active) {
            throw ValidationException::withMessages([
                'driver' => 'La cuenta del repartidor no está activa.',
            ]);
        }

        if (in_array($driver->availability_status, [
            DriverAvailabilityStatus::Offline,
            DriverAvailabilityStatus::Paused,
        ], true)) {
            throw ValidationException::withMessages([
                'driver' => 'El repartidor no está disponible para nuevos pedidos.',
            ]);
        }

        if ($order->isPlatformManaged()) {
            if ($driver->driver_scope !== DriverScope::Platform) {
                throw ValidationException::withMessages([
                    'driver' => 'Este pedido lo atienden repartidores de plataforma.',
                ]);
            }
        } else {
            $business = $order->branch?->business;

            if ($business === null || $order->branch === null) {
                throw ValidationException::withMessages([
                    'order' => 'El pedido no tiene establecimiento válido.',
                ]);
            }

            $deliveryMode = $business->delivery_mode;

            if ($deliveryMode === BusinessDeliveryMode::None) {
                throw ValidationException::withMessages([
                    'order' => 'Este establecimiento no usa repartidores.',
                ]);
            }

            if (! $this->matchesDeliveryMode($driver, $deliveryMode, $business->id)) {
                throw ValidationException::withMessages([
                    'driver' => 'El repartidor no es elegible para la modalidad de entrega de este negocio.',
                ]);
            }
        }

        if ($order->assigned_driver_id !== null) {
            throw ValidationException::withMessages([
                'order' => 'Este pedido ya fue tomado por otro repartidor.',
            ]);
        }

        if (! in_array($order->order_status, OrderActiveStatuses::offerable(), true)) {
            throw ValidationException::withMessages([
                'order' => 'El pedido no está disponible para asignación.',
            ]);
        }

        $rejected = $order->assignments()
            ->where('driver_id', $driver->id)
            ->whereIn('status', [
                DriverAssignmentStatus::Rejected->value,
                DriverAssignmentStatus::Expired->value,
            ])
            ->exists();

        if ($rejected) {
            throw ValidationException::withMessages([
                'order' => 'Ya rechazaste o dejaste expirar este pedido.',
            ]);
        }

        $this->assertCapacity($driver, $order);
    }

    public function matchesDeliveryMode(Driver $driver, BusinessDeliveryMode $mode, int $businessId): bool
    {
        $linkedToBusiness = $driver->businesses->contains('id', $businessId)
            || $driver->businesses()->whereKey($businessId)->exists();

        return match ($mode) {
            BusinessDeliveryMode::None => false,
            BusinessDeliveryMode::OwnDrivers => $driver->driver_scope === DriverScope::BusinessOnly
                && $linkedToBusiness,
            BusinessDeliveryMode::PlatformDrivers => $driver->driver_scope === DriverScope::Platform,
            BusinessDeliveryMode::Hybrid => (
                $driver->driver_scope === DriverScope::BusinessOnly && $linkedToBusiness
            ) || $driver->driver_scope === DriverScope::Platform,
        };
    }

    /**
     * HYBRID: prefer own drivers when at least one eligible own driver is available.
     * Listing still shows platform drivers when no own drivers are free — simple rule for V1.
     */
    public function shouldPreferOwnDrivers(Order $order): bool
    {
        $order->loadMissing('branch.business');

        return $order->branch?->business?->delivery_mode === BusinessDeliveryMode::Hybrid;
    }

    private function assertCapacity(Driver $driver, Order $order): void
    {
        $active = $this->activeOrders->activeOrdersFor($driver);
        $max = (int) config('business.dispatch.max_active_orders_per_driver', 3);

        if ($active->isEmpty()) {
            return;
        }

        if ($active->count() >= $max) {
            throw ValidationException::withMessages([
                'driver' => 'Alcanzaste el límite de pedidos activos.',
            ]);
        }

        $activeBranchId = $active->first()?->branch_id;

        if ($activeBranchId === null || $order->branch_id === null) {
            return;
        }

        if ($active->contains(fn (Order $activeOrder): bool => (int) $activeOrder->branch_id !== (int) $activeBranchId)) {
            throw ValidationException::withMessages([
                'driver' => 'Tienes pedidos activos de distintas sucursales.',
            ]);
        }

        if ((int) $order->branch_id !== (int) $activeBranchId) {
            throw ValidationException::withMessages([
                'driver' => 'Solo puedes tomar pedidos de la misma sucursal mientras estés en servicio.',
            ]);
        }
    }
}
