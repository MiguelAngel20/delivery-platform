<?php

namespace App\Http\Requests\Business\Catalog;

use App\Models\ProductCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateProductCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $category = $this->category();

        return $category !== null
            && ($this->user()?->can('update', $category) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $category = $this->category();

        return [
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('product_categories', 'id')
                    ->where('branch_id', $category?->branch_id)
                    ->whereNull('parent_id')
                    ->whereNull('deleted_at')
                    ->whereNot('id', $category?->id),
            ],
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'parent_id.exists' => 'La categoría padre debe existir en la misma sucursal y no puede ser otra subcategoría.',
            'name.required' => 'El nombre de la categoría es obligatorio.',
            'name.max' => 'El nombre no puede superar :max caracteres.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'parent_id' => 'categoría padre',
            'name' => 'nombre',
            'description' => 'descripción',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $category = $this->category();

            if ($category === null || ! $this->filled('parent_id')) {
                return;
            }

            if ($category->children()->exists()) {
                $validator->errors()->add(
                    'parent_id',
                    'No puedes convertir en subcategoría una categoría que ya tiene subcategorías.',
                );
            }
        });
    }

    private function category(): ?ProductCategory
    {
        $category = $this->route('category') ?? $this->route('subcategory');

        return $category instanceof ProductCategory ? $category : null;
    }
}
