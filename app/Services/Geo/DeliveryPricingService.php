<?php

namespace App\Services\Geo;

use App\Models\BusinessBranch;

/**
 * V1 keeps the configured flat delivery fee.
 * Distance/zone inputs are accepted so future km/zone rules can plug in
 * without changing call sites.
 */
final class DeliveryPricingService
{
    /**
     * @param  array{distance_meters?: int|null, coverage_zone_id?: int|null}  $context
     */
    public function quote(?BusinessBranch $branch = null, array $context = []): string
    {
        unset($branch, $context);

        return number_format((float) config('business.orders.delivery_fee', 0), 2, '.', '');
    }
}
