<?php

namespace App\Support;

use App\Models\CustomOrderRequest;

final class CustomOrderBroadcastPayload
{
    /**
     * @return array<string, mixed>
     */
    public static function base(CustomOrderRequest $request): array
    {
        $request->loadMissing(['customer.user', 'quotes']);

        return [
            'custom_order_request_id' => $request->id,
            'customer_id' => $request->customer_id,
            'status' => $request->status->value,
            'status_label' => $request->status->label(),
            'establishment_name' => $request->establishment_name,
            'quoted_order_id' => $request->quoted_order_id,
            'assigned_admin_user_id' => $request->assigned_admin_user_id,
            'updated_at' => $request->updated_at?->toIso8601String(),
        ];
    }
}
