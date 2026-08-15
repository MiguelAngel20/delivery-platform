<?php

namespace App\Http\Requests\Business\Catalog;

use App\Enums\PromotionStatus;
use App\Models\Promotion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePromotionRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Promotion $promotion */
        $promotion = $this->route('promotion');

        return $this->user()?->can('update', $promotion) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('items'))) {
            $decoded = json_decode($this->input('items'), true);
            $this->merge([
                'items' => is_array($decoded) ? $decoded : [],
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Promotion $promotion */
        $promotion = $this->route('promotion');
        $branchId = $promotion->branch_id;

        return [
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'promotion_price' => ['required', 'numeric', 'min:0'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'status' => ['required', Rule::enum(PromotionStatus::class)],
            'items' => ['required', 'array', 'min:1'],
            'items.*.is_external_item' => ['required', 'boolean'],
            'items.*.product_id' => [
                'nullable',
                'integer',
                Rule::exists('products', 'id')
                    ->where('branch_id', $branchId)
                    ->whereNull('deleted_at'),
            ],
            'items.*.name' => ['nullable', 'string', 'max:150'],
            'items.*.description' => ['nullable', 'string'],
            'items.*.quantity' => ['nullable', 'numeric', 'min:0.01'],
            'items.*.original_price' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            foreach ($this->input('items', []) as $index => $item) {
                $isExternal = (bool) ($item['is_external_item'] ?? false);

                if ($isExternal) {
                    if (blank($item['name'] ?? null)) {
                        $validator->errors()->add("items.{$index}.name", 'El ítem externo requiere nombre.');
                    }

                    if (! blank($item['product_id'] ?? null)) {
                        $validator->errors()->add("items.{$index}.product_id", 'Un ítem externo no debe tener product_id.');
                    }

                    continue;
                }

                if (blank($item['product_id'] ?? null)) {
                    $validator->errors()->add("items.{$index}.product_id", 'Debes seleccionar un producto del menú.');
                }
            }
        });
    }
}
