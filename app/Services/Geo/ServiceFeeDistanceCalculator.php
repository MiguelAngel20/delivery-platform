<?php

namespace App\Services\Geo;

use App\Models\ServiceFeeDistanceSetting;

final class ServiceFeeDistanceCalculator
{
    /**
     * @param  array{base_meters: int, base_fee: float|string, step_meters: int, step_fee: float|string}|ServiceFeeDistanceSetting|null  $settings
     */
    public function calculate(int $distanceMeters, array|ServiceFeeDistanceSetting|null $settings = null): string
    {
        $config = $this->normalize($settings);
        $distanceMeters = max(0, $distanceMeters);

        $baseMeters = $config['base_meters'];
        $baseFee = $config['base_fee'];
        $stepMeters = $config['step_meters'];
        $stepFee = $config['step_fee'];

        if ($distanceMeters <= $baseMeters) {
            return number_format($baseFee, 2, '.', '');
        }

        $extraMeters = $distanceMeters - $baseMeters;
        $steps = (int) ceil($extraMeters / $stepMeters);
        $fee = $baseFee + ($steps * $stepFee);

        return number_format($fee, 2, '.', '');
    }

    /**
     * @param  array{base_meters: int, base_fee: float|string, step_meters: int, step_fee: float|string}|ServiceFeeDistanceSetting|null  $settings
     * @return array{base_meters: int, base_fee: float, step_meters: int, step_fee: float}
     */
    private function normalize(array|ServiceFeeDistanceSetting|null $settings): array
    {
        if ($settings instanceof ServiceFeeDistanceSetting) {
            return [
                'base_meters' => max(1, (int) $settings->base_meters),
                'base_fee' => (float) $settings->base_fee,
                'step_meters' => max(1, (int) $settings->step_meters),
                'step_fee' => (float) $settings->step_fee,
            ];
        }

        if (is_array($settings)) {
            return [
                'base_meters' => max(1, (int) ($settings['base_meters'] ?? 1000)),
                'base_fee' => (float) ($settings['base_fee'] ?? 50),
                'step_meters' => max(1, (int) ($settings['step_meters'] ?? 500)),
                'step_fee' => (float) ($settings['step_fee'] ?? 5),
            ];
        }

        $current = ServiceFeeDistanceSetting::current();

        return [
            'base_meters' => max(1, (int) $current->base_meters),
            'base_fee' => (float) $current->base_fee,
            'step_meters' => max(1, (int) $current->step_meters),
            'step_fee' => (float) $current->step_fee,
        ];
    }
}
