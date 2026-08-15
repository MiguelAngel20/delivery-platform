<?php

namespace App\Http\Requests\Customer;

use App\Enums\OrderAddressSource;
use App\Models\CustomOrderRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', CustomOrderRequest::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'establishment_name' => ['nullable', 'string', 'max:150'],
            'business_id' => ['nullable', 'integer', 'exists:businesses,id'],
            'branch_id' => ['nullable', 'integer', 'exists:business_branches,id'],
            'description' => ['required', 'string', 'max:2000'],
            'customer_notes' => ['nullable', 'string', 'max:1000'],
            'merchant_address' => ['nullable', 'string', 'max:500'],
            'merchant_phone' => ['nullable', 'string', 'max:30'],
            'merchant_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'merchant_longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'merchant_formatted_address' => ['nullable', 'string', 'max:500'],
            'merchant_place_id' => ['nullable', 'string', 'max:255'],
            'merchant_reference' => ['nullable', 'string'],
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
