<?php

namespace App\Support;

use App\Enums\CustomerTrustLevel;
use App\Models\Customer;
use App\Models\CustomerMetric;
use App\Models\Driver;
use App\Models\DriverMetric;
use App\Services\Reputation\CustomerReputationService;
use App\Services\Reputation\DriverReputationService;

final class ReputationPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function customerForSelf(Customer $customer): array
    {
        $metrics = self::customerMetrics($customer);

        return [
            'verified' => $customer->trust_level->value !== 'new' || ($metrics?->completed_orders ?? 0) > 0,
            'public_label' => $customer->trust_level->publicLabel(),
            'is_frequent' => $customer->trust_level->isFrequent(),
            'completed_orders' => $metrics?->completed_orders ?? 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function customerForDriver(Customer $customer): array
    {
        $metrics = self::customerMetrics($customer);
        $name = $customer->user?->name ?? 'Cliente';

        return [
            'id' => $customer->id,
            'name' => self::publicName($name),
            'verified' => $customer->trust_level !== CustomerTrustLevel::New
                || ($metrics?->completed_orders ?? 0) > 0,
            'public_label' => $customer->trust_level->publicLabel(),
            'is_frequent' => $customer->trust_level->isFrequent(),
            'completed_orders' => $metrics?->completed_orders ?? 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function customerForBusiness(Customer $customer): array
    {
        $metrics = self::customerMetrics($customer);

        return [
            'id' => $customer->id,
            'name' => $customer->user?->name,
            'phone' => $customer->user?->phone,
            'completed_orders' => $metrics?->completed_orders ?? 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function customerForAdmin(Customer $customer): array
    {
        $metrics = self::customerMetrics($customer);

        return [
            'id' => $customer->id,
            'name' => $customer->user?->name,
            'email' => $customer->user?->email,
            'phone' => $customer->user?->phone,
            'user_status' => $customer->user?->status->value,
            'user_status_label' => $customer->user?->status->label(),
            'trust_level' => $customer->trust_level->value,
            'trust_level_label' => $customer->trust_level->label(),
            'trust_level_tone' => $customer->trust_level->tone(),
            'trust_score' => $metrics?->trust_score,
            'total_orders' => $metrics?->total_orders ?? 0,
            'completed_orders' => $metrics?->completed_orders ?? 0,
            'cancelled_orders' => $metrics?->cancelled_orders ?? 0,
            'late_cancellations' => $metrics?->late_cancellations ?? 0,
            'rejected_at_delivery' => $metrics?->rejected_at_delivery ?? 0,
            'incident_count' => $metrics?->incident_count ?? 0,
            'responsible_incidents' => $metrics?->responsible_incidents ?? 0,
            'payment_incidents' => $metrics?->payment_incidents ?? 0,
            'requires_review' => $customer->trust_level->isRestricted() || $customer->trust_level->isBlocked(),
            'last_recalculated_at' => $metrics?->last_recalculated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function driverForSelf(Driver $driver): array
    {
        $metrics = self::driverMetrics($driver);

        return [
            'average_rating' => $metrics?->average_rating,
            'total_ratings' => $metrics?->total_ratings ?? 0,
            'completed_orders' => $metrics?->completed_orders ?? 0,
            'quality_label' => $metrics !== null
                ? app(DriverReputationService::class)->qualityLabel((float) $metrics->trust_score)
                : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function driverForAdmin(Driver $driver): array
    {
        $metrics = self::driverMetrics($driver);
        $score = $metrics !== null ? (float) $metrics->trust_score : null;

        return [
            'id' => $driver->id,
            'name' => $driver->user?->name,
            'email' => $driver->user?->email,
            'phone' => $driver->user?->phone,
            'user_status' => $driver->user?->status->value,
            'user_status_label' => $driver->user?->status->label(),
            'approval_status' => $driver->approval_status->value,
            'approval_status_label' => $driver->approval_status->label(),
            'availability_status' => $driver->availability_status->value,
            'availability_status_label' => $driver->availability_status->label(),
            'offered_orders' => $metrics?->offered_orders ?? 0,
            'accepted_orders' => $metrics?->accepted_orders ?? 0,
            'rejected_orders' => $metrics?->rejected_orders ?? 0,
            'completed_orders' => $metrics?->completed_orders ?? 0,
            'cancelled_orders' => $metrics?->cancelled_orders ?? 0,
            'responsible_cancellations' => $metrics?->responsible_cancellations ?? 0,
            'incident_count' => $metrics?->incident_count ?? 0,
            'responsible_incidents' => $metrics?->responsible_incidents ?? 0,
            'average_rating' => $metrics?->average_rating,
            'total_ratings' => $metrics?->total_ratings ?? 0,
            'trust_score' => $metrics?->trust_score,
            'quality_label' => $score !== null
                ? app(DriverReputationService::class)->qualityLabel($score)
                : null,
            'requires_review' => $metrics?->requiresReview() ?? false,
            'last_recalculated_at' => $metrics?->last_recalculated_at?->toIso8601String(),
        ];
    }

    public static function publicName(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $first = $parts[0] ?? 'Cliente';
        $lastInitial = isset($parts[1]) ? mb_strtoupper(mb_substr($parts[1], 0, 1)).'.' : '';

        return trim($first.' '.$lastInitial);
    }

    private static function customerMetrics(Customer $customer): ?CustomerMetric
    {
        $customer->loadMissing('metrics');

        if ($customer->metrics === null) {
            app(CustomerReputationService::class)->recalculate($customer);
            $customer->refresh()->load('metrics');
        }

        return $customer->metrics;
    }

    private static function driverMetrics(Driver $driver): ?DriverMetric
    {
        $driver->loadMissing(['metrics', 'user']);

        if ($driver->metrics === null) {
            app(DriverReputationService::class)->recalculate($driver);
            $driver->refresh()->load(['metrics', 'user']);
        }

        return $driver->metrics;
    }
}
