<?php

namespace App\Services\Finance;

use App\Enums\CollectionParty;
use App\Enums\PaymentMethod;
use App\Models\Order;
use InvalidArgumentException;

final class RevenueAllocationService
{
    /**
     * @return array{
     *     business_amount: string,
     *     driver_earning: string,
     *     platform_earning: string,
     *     collection_party: CollectionParty
     * }
     */
    public function allocate(Order $order): array
    {
        $driverShare = (string) config('business.finance.allocation.driver_service_fee_share', 1);
        $platformShare = (string) config('business.finance.allocation.platform_service_fee_share', 0);

        if (bccomp(bcadd($driverShare, $platformShare, 4), '1.0000', 4) !== 0) {
            throw new InvalidArgumentException('Las participaciones del service fee deben sumar 1.');
        }

        $serviceFee = (string) $order->service_fee;
        $deliveryFee = (string) $order->delivery_fee;

        $driverFromService = bcmul($serviceFee, $driverShare, 2);
        $platformFromService = bcmul($serviceFee, $platformShare, 2);

        // Absorb rounding residue into platform so totals stay consistent.
        $allocatedService = bcadd($driverFromService, $platformFromService, 2);
        if (bccomp($allocatedService, $serviceFee, 2) !== 0) {
            $platformFromService = bcadd(
                $platformFromService,
                bcsub($serviceFee, $allocatedService, 2),
                2,
            );
        }

        $driverEarning = $driverFromService;
        if ((bool) config('business.finance.allocation.driver_receives_delivery_fee', true)) {
            $driverEarning = bcadd($driverEarning, $deliveryFee, 2);
        }

        $collectionParty = CollectionParty::tryFrom(
            (string) config('business.finance.cash.collection_party', CollectionParty::Driver->value),
        ) ?? CollectionParty::Driver;

        if ($order->payment_method !== PaymentMethod::Cash) {
            $collectionParty = CollectionParty::tryFrom(
                (string) config('business.finance.cash.collection_party', CollectionParty::Driver->value),
            ) ?? CollectionParty::Driver;
        }

        $productRevenue = (string) $order->subtotal_after_discount;
        $businessAmount = $productRevenue;
        $platformEarning = $platformFromService;

        if ($order->isPlatformManaged()) {
            $acquisition = $this->acquisitionTotal($order);
            $businessAmount = $acquisition;
            $productMargin = bcsub($productRevenue, $acquisition, 2);
            $platformEarning = bcadd($platformFromService, $productMargin, 2);
        }

        return [
            'business_amount' => $businessAmount,
            'driver_earning' => $driverEarning,
            'platform_earning' => $platformEarning,
            'collection_party' => $collectionParty,
        ];
    }

    private function acquisitionTotal(Order $order): string
    {
        $order->loadMissing('items');

        $total = '0.00';

        foreach ($order->items as $item) {
            $unitCost = $item->unit_acquisition_cost !== null
                ? (string) $item->unit_acquisition_cost
                : (string) $item->unit_final_price;

            $total = bcadd($total, bcmul($unitCost, (string) $item->quantity, 2), 2);
        }

        return $total;
    }
}
