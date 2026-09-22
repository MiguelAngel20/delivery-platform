<?php

namespace App\Services\Drivers;

use App\Enums\LoyaltyRewardType;
use App\Enums\OrderStatus;
use App\Models\Driver;
use App\Models\Order;
use App\Models\OrderFinancial;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class DriverCommissionService
{
    public function applyOnDelivery(Order $order, Driver $driver): void
    {
        $driver->refresh();

        if (! $driver->pays_commission) {
            return;
        }

        // Pedido con recompensa por 5 pedidos: toda la ganancia es del repartidor.
        // No aplica al 50% de bienvenida de los primeros clientes (launch_fifty).
        if ($order->loyalty_reward_type === LoyaltyRewardType::StreakReward) {
            return;
        }

        $rate = number_format((float) $driver->commission_per_order, 2, '.', '');

        if (bccomp($rate, '0.00', 2) <= 0) {
            return;
        }

        $order->loadMissing('financial');
        $financial = $order->financial;

        if ($financial === null) {
            return;
        }

        if (bccomp((string) $financial->driver_commission, '0.00', 2) > 0) {
            return;
        }

        $gross = (string) $financial->driver_earning;
        $commission = bccomp($rate, $gross, 2) === 1 ? $gross : $rate;
        $net = bcsub($gross, $commission, 2);
        $platform = bcadd((string) $financial->platform_earning, $commission, 2);

        $financial->forceFill([
            'driver_earning' => $net,
            'driver_commission' => $commission,
            'platform_earning' => $platform,
        ])->save();
    }

    public function unpaidAmount(Driver $driver, ?Carbon $before = null): string
    {
        $before ??= Carbon::today();

        $amount = OrderFinancial::query()
            ->whereNull('commission_settled_at')
            ->where('driver_commission', '>', 0)
            ->whereHas('order', function ($query) use ($driver, $before): void {
                $query->where('assigned_driver_id', $driver->id)
                    ->where('order_status', OrderStatus::Delivered)
                    ->whereNotNull('delivered_at')
                    ->where('delivered_at', '<', $before);
            })
            ->sum('driver_commission');

        return number_format((float) $amount, 2, '.', '');
    }

    public function isBlockedFromAccepting(Driver $driver): bool
    {
        return bccomp($this->unpaidAmount($driver), '0.00', 2) === 1;
    }

    public function assertCanAcceptOrders(Driver $driver): void
    {
        $owed = $this->unpaidAmount($driver);

        if (bccomp($owed, '0.00', 2) !== 1) {
            return;
        }

        throw ValidationException::withMessages([
            'driver' => "Debes liquidar tu comisión pendiente de \${$owed} con ChisDrive antes de aceptar pedidos.",
        ]);
    }

    /**
     * @return array{
     *     blocked: bool,
     *     amount: string,
     *     message: string
     * }|null
     */
    public function debtPayload(Driver $driver): ?array
    {
        $amount = $this->unpaidAmount($driver);

        if (bccomp($amount, '0.00', 2) !== 1) {
            return null;
        }

        return [
            'blocked' => true,
            'amount' => $amount,
            'message' => "Tu pago de comisión pendiente es \${$amount}. Si ya realizaste el pago, comunícate con el administrador para su verificación.",
        ];
    }

    public function markOutstandingPaid(Driver $driver, User $admin): int
    {
        return DB::transaction(function () use ($driver, $admin): int {
            $ids = OrderFinancial::query()
                ->whereNull('commission_settled_at')
                ->where('driver_commission', '>', 0)
                ->whereHas('order', function ($query) use ($driver): void {
                    $query->where('assigned_driver_id', $driver->id)
                        ->where('order_status', OrderStatus::Delivered);
                })
                ->lockForUpdate()
                ->pluck('id');

            if ($ids->isEmpty()) {
                return 0;
            }

            return OrderFinancial::query()
                ->whereIn('id', $ids)
                ->update([
                    'commission_settled_at' => now(),
                    'commission_settled_by' => $admin->id,
                    'updated_at' => now(),
                ]);
        });
    }

    /**
     * @param  array{pays_commission: bool, commission_per_order: float|string}  $data
     */
    public function updateSettings(Driver $driver, array $data): Driver
    {
        $pays = (bool) $data['pays_commission'];
        $amount = number_format((float) ($data['commission_per_order'] ?? 0), 2, '.', '');

        if (! $pays) {
            $amount = '0.00';
        }

        $driver->forceFill([
            'pays_commission' => $pays,
            'commission_per_order' => $amount,
        ])->save();

        return $driver->refresh();
    }
}
