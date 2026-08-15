<?php

namespace App\Http\Requests\Admin;

use App\Models\CustomOrderRequest;
use Illuminate\Foundation\Http\FormRequest;

class StoreCustomOrderQuoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var CustomOrderRequest $customOrder */
        $customOrder = $this->route('customOrder');

        return $this->user()?->can('quote', $customOrder) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'service_fee' => ['nullable', 'numeric', 'min:0'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.acquisition_cost' => ['nullable', 'numeric', 'min:0'],
            'items.*.notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
