<?php

namespace App\Http\Requests\Customer;

use App\Enums\OrderAddressSource;
use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Order::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'branch_id' => ['required', 'integer', 'exists:business_branches,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required_without:items.*.promotion_id', 'integer'],
            'items.*.promotion_id' => ['required_without:items.*.product_id', 'integer'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.special_instructions' => ['nullable', 'string', 'max:500'],
            'items.*.selected_options' => ['nullable', 'array'],
            'items.*.selected_options.*.option_id' => ['required', 'integer'],
            'items.*.selected_options.*.action' => ['required', 'string', Rule::in(['removed', 'added', 'selected'])],
            'items.*.promotion_items' => ['required_with:items.*.promotion_id', 'array'],
            'items.*.promotion_items.*.promotion_item_id' => ['required', 'integer'],
            'items.*.promotion_items.*.special_instructions' => ['nullable', 'string', 'max:500'],
            'items.*.promotion_items.*.selected_options' => ['nullable', 'array'],
            'items.*.promotion_items.*.selected_options.*.option_id' => ['required', 'integer'],
            'items.*.promotion_items.*.selected_options.*.action' => ['required', 'string', Rule::in(['removed', 'added', 'selected'])],
            'delivery' => ['required', 'array'],
            'delivery.source' => ['required', Rule::enum(OrderAddressSource::class)],
            'delivery.customer_address_id' => ['nullable', 'integer'],
            'delivery.address_text' => ['nullable', 'string', 'max:255'],
            'delivery.formatted_address' => ['nullable', 'string', 'max:500'],
            'delivery.reference' => ['nullable', 'string'],
            'delivery.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'delivery.longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'delivery.place_id' => ['nullable', 'string', 'max:255'],
            'delivery.google_maps_url' => ['nullable', 'string'],
        ];
    }
}
