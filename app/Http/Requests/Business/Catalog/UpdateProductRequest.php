<?php

namespace App\Http\Requests\Business\Catalog;

use App\Enums\ProductOptionGroupType;
use App\Models\Product;
use App\Support\Catalog\ProductFormValidation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    use ProductFormValidation;

    public function authorize(): bool
    {
        /** @var Product $product */
        $product = $this->route('product');

        return $this->user()?->can('update', $product) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('option_groups'))) {
            $decoded = json_decode($this->input('option_groups'), true);
            $this->merge([
                'option_groups' => is_array($decoded) ? $decoded : [],
            ]);
        }

        foreach (['is_available', 'is_active', 'allow_special_instructions'] as $field) {
            if ($this->has($field)) {
                $this->merge([
                    $field => filter_var($this->input($field), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false,
                ]);
            }
        }

        $this->sanitizeProductOptionGroups();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Product $product */
        $product = $this->route('product');
        $branchId = $product->branch_id;

        return [
            'product_category_id' => [
                'nullable',
                'integer',
                Rule::exists('product_categories', 'id')
                    ->where('branch_id', $branchId)
                    ->whereNull('deleted_at'),
            ],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'list_price' => ['required', 'numeric', 'min:0'],
            'is_available' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'allow_special_instructions' => ['sometimes', 'boolean'],
            'option_groups' => ['nullable', 'array'],
            'option_groups.*.name' => ['required_with:option_groups', 'string', 'max:100'],
            'option_groups.*.type' => ['required_with:option_groups', Rule::enum(ProductOptionGroupType::class)],
            'option_groups.*.is_required' => ['sometimes', 'boolean'],
            'option_groups.*.min_selection' => ['required_with:option_groups', 'integer', 'min:0'],
            'option_groups.*.max_selection' => ['required_with:option_groups', 'integer', 'gte:option_groups.*.min_selection'],
            'option_groups.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'option_groups.*.is_active' => ['sometimes', 'boolean'],
            'option_groups.*.options' => ['required', 'array', 'min:1'],
            'option_groups.*.options.*.name' => ['required', 'string', 'max:100'],
            'option_groups.*.options.*.description' => ['nullable', 'string'],
            'option_groups.*.options.*.price_modifier' => ['nullable', 'numeric'],
            'option_groups.*.options.*.is_default' => ['sometimes', 'boolean'],
            'option_groups.*.options.*.is_available' => ['sometimes', 'boolean'],
            'option_groups.*.options.*.sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
