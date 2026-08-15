<?php

namespace App\Support;

use App\Models\CustomOrderRequest;
use App\Models\OrderQuote;

final class CustomOrderRequestData
{
    /**
     * @return array<string, mixed>
     */
    public static function transform(CustomOrderRequest $request): array
    {
        $request->loadMissing([
            'customer.user',
            'business',
            'branch',
            'deliveryAddress',
            'assignedAdmin',
            'quotedOrder',
            'quotes.items',
        ]);

        $quote = $request->quotes->sortByDesc('id')->first();

        return [
            'id' => $request->id,
            'status' => $request->status->value,
            'status_label' => $request->status->label(),
            'establishment_name' => $request->establishment_name
                ?: $request->business?->name,
            'description' => $request->description,
            'customer_notes' => $request->customer_notes,
            'merchant_address' => $request->merchant_address,
            'merchant_phone' => $request->merchant_phone,
            'merchant_latitude' => $request->merchant_latitude !== null ? (string) $request->merchant_latitude : null,
            'merchant_longitude' => $request->merchant_longitude !== null ? (string) $request->merchant_longitude : null,
            'merchant_formatted_address' => $request->merchant_formatted_address,
            'merchant_place_id' => $request->merchant_place_id,
            'merchant_reference' => $request->merchant_reference,
            'assigned_admin_user_id' => $request->assigned_admin_user_id,
            'assigned_admin_name' => $request->assignedAdmin?->name,
            'quoted_order_id' => $request->quoted_order_id,
            'quoted_order_number' => $request->quotedOrder?->order_number,
            'created_at' => $request->created_at?->toIso8601String(),
            'customer' => [
                'id' => $request->customer_id,
                'name' => $request->customer?->user?->name,
            ],
            'business' => $request->business ? [
                'id' => $request->business->id,
                'name' => $request->business->name,
            ] : null,
            'branch' => $request->branch ? [
                'id' => $request->branch->id,
                'name' => $request->branch->name,
            ] : null,
            'delivery' => self::delivery($request),
            'latest_quote' => $quote ? self::quote($quote) : null,
            'is_open' => $request->status->isOpen(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function listRow(CustomOrderRequest $request): array
    {
        $data = self::transform($request);

        return [
            'id' => $data['id'],
            'status' => $data['status'],
            'status_label' => $data['status_label'],
            'establishment_name' => $data['establishment_name'],
            'description' => $data['description'],
            'customer' => $data['customer'],
            'assigned_admin_name' => $data['assigned_admin_name'],
            'created_at' => $data['created_at'],
            'quoted_order_number' => $data['quoted_order_number'],
            'latest_total' => $data['latest_quote']['total'] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function quote(OrderQuote $quote): array
    {
        $quote->loadMissing('items');

        return [
            'id' => $quote->id,
            'type' => $quote->type->value,
            'status' => $quote->status->value,
            'status_label' => $quote->status->label(),
            'subtotal' => (string) $quote->subtotal,
            'service_fee' => (string) $quote->service_fee,
            'discount_amount' => (string) $quote->discount_amount,
            'total' => (string) $quote->total,
            'created_at' => $quote->created_at?->toIso8601String(),
            'items' => $quote->items->map(fn ($item): array => [
                'id' => $item->id,
                'description' => $item->description,
                'quantity' => (string) $item->quantity,
                'unit_price' => (string) $item->unit_price,
                'subtotal' => (string) $item->subtotal,
                'acquisition_cost' => $item->acquisition_cost !== null
                    ? (string) $item->acquisition_cost
                    : null,
                'notes' => $item->notes,
            ])->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function delivery(CustomOrderRequest $request): ?array
    {
        if ($request->deliveryAddress !== null) {
            return [
                'source' => 'saved_address',
                'address_text' => $request->deliveryAddress->address_text,
                'reference' => $request->deliveryAddress->reference,
            ];
        }

        $temporary = $request->temporary_delivery_address;

        if (! is_array($temporary) || blank($temporary['address_text'] ?? null)) {
            return null;
        }

        return [
            'source' => 'temporary',
            'address_text' => $temporary['address_text'],
            'reference' => $temporary['reference'] ?? null,
        ];
    }
}
