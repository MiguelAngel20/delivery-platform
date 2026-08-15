<?php

namespace App\Support;

use App\Enums\BusinessUserRole;
use App\Enums\BusinessUserStatus;
use App\Models\Business;
use App\Models\BusinessUser;

final class BusinessMembershipData
{
    /**
     * @return array{
     *     roles: list<array{value: string, label: string}>,
     *     statuses: list<array{value: string, label: string}>,
     *     branches: list<array{id: int, name: string, status: string, status_label: string}>
     * }
     */
    public static function formOptions(Business $business): array
    {
        return [
            'roles' => collect(BusinessUserRole::cases())
                ->map(fn (BusinessUserRole $role): array => [
                    'value' => $role->value,
                    'label' => $role->label(),
                ])
                ->values()
                ->all(),
            'statuses' => collect(BusinessUserStatus::cases())
                ->map(fn (BusinessUserStatus $status): array => [
                    'value' => $status->value,
                    'label' => $status->label(),
                ])
                ->values()
                ->all(),
            'branches' => $business->branches()
                ->orderBy('name')
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
    public static function transform(BusinessUser $membership): array
    {
        return [
            'id' => $membership->id,
            'role' => $membership->role->value,
            'role_label' => $membership->role->label(),
            'status' => $membership->status->value,
            'status_label' => $membership->status->label(),
            'user' => [
                'id' => $membership->user?->id,
                'first_name' => $membership->user?->first_name,
                'last_name' => $membership->user?->last_name,
                'name' => $membership->user?->name,
                'email' => $membership->user?->email,
                'phone' => $membership->user?->phone,
            ],
            'branches' => $membership->relationLoaded('branches')
                ? $membership->branches->map(fn ($branch): array => [
                    'id' => $branch->id,
                    'name' => $branch->name,
                ])->values()->all()
                : [],
            'branch_ids' => $membership->relationLoaded('branches')
                ? $membership->branches->pluck('id')->map(fn ($id): int => (int) $id)->values()->all()
                : [],
        ];
    }
}
