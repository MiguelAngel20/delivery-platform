<?php

namespace App\Support;

use App\Models\BusinessBranch;

final class BusinessBranchData
{
    /**
     * @return array<string, mixed>
     */
    public static function transform(BusinessBranch $branch): array
    {
        return [
            'id' => $branch->id,
            'name' => $branch->name,
            'phone' => $branch->phone,
            'address_text' => $branch->address_text,
            'reference' => $branch->reference,
            'latitude' => $branch->latitude,
            'longitude' => $branch->longitude,
            'google_maps_url' => $branch->google_maps_url,
            'opening_hours' => BusinessHours::present($branch->opening_hours),
            'schedule_label' => BusinessHours::todayLabel($branch->opening_hours),
            'status' => $branch->status->value,
            'status_label' => $branch->status->label(),
        ];
    }
}
