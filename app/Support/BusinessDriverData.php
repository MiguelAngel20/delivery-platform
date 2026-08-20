<?php

namespace App\Support;

use App\Enums\DriverApprovalStatus;
use App\Models\Business;
use App\Models\Driver;
use App\Models\User;

final class BusinessDriverData
{
    /**
     * @return array{
     *     branches: list<array{id: int, name: string, status: string, status_label: string}>
     * }
     */
    public static function formOptions(Business $business, ?User $actor = null): array
    {
        $query = $business->branches()->orderBy('name');

        if ($actor !== null) {
            $accessibleBranchIds = app(BusinessAccess::class)->accessibleBranchIds($actor, $business);

            if ($accessibleBranchIds === []) {
                return ['branches' => []];
            }

            $query->whereIn('id', $accessibleBranchIds);
        }

        return [
            'branches' => $query
                ->get(['id', 'name', 'status'])
                ->map(fn ($branch): array => [
                    'id' => $branch->id,
                    'name' => $branch->name,
                    'status' => $branch->status->value,
                    'status_label' => $branch->status->label(),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function transform(Driver $driver, Business $business): array
    {
        $branches = $driver->relationLoaded('branches')
            ? $driver->branches->filter(
                fn ($branch): bool => (int) $branch->business_id === (int) $business->id,
            )
            : collect();

        return [
            'id' => $driver->id,
            'approval_status' => $driver->approval_status->value,
            'approval_status_label' => $driver->approval_status->label(),
            'availability_status' => $driver->availability_status->value,
            'availability_status_label' => $driver->availability_status->label(),
            'is_approved' => $driver->approval_status === DriverApprovalStatus::Approved,
            'user' => [
                'id' => $driver->user?->id,
                'first_name' => $driver->user?->first_name,
                'last_name' => $driver->user?->last_name,
                'name' => $driver->user?->name,
                'email' => $driver->user?->email,
                'phone' => $driver->user?->phone,
            ],
            'branches' => $branches
                ->map(fn ($branch): array => [
                    'id' => $branch->id,
                    'name' => $branch->name,
                ])
                ->values()
                ->all(),
            'branch_ids' => $branches
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->values()
                ->all(),
        ];
    }
}
