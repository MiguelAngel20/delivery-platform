<?php

namespace App\Http\Requests\Business\Catalog;

use App\Models\ProductCategory;
use App\Support\CategorySchedule;
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

    protected function prepareForValidation(): void
    {
        if ($this->has('schedule_hours')) {
            $this->merge([
                'schedule_hours' => CategorySchedule::prepareInput($this->input('schedule_hours')),
            ]);
        }

        if ($this->has('has_schedule')) {
            $this->merge([
                'has_schedule' => filter_var($this->input('has_schedule'), FILTER_VALIDATE_BOOLEAN),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $category = $this->category();
        $isPrincipal = $category?->isRoot() ?? blank($this->input('parent_id'));
        $hasSchedule = filter_var($this->input('has_schedule'), FILTER_VALIDATE_BOOLEAN);

        $rules = [
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
            'has_schedule' => ['sometimes', 'boolean'],
        ];

        if ($isPrincipal && $hasSchedule) {
            $rules = [...$rules, ...CategorySchedule::validationRules(required: true)];
        } else {
            $rules['schedule_hours'] = ['nullable'];
        }

        return $rules;
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
            'schedule_hours' => 'horario',
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

        $category = $this->category();

        if ($category === null || ! $category->isRoot()) {
            return;
        }

        if (! filter_var($this->input('has_schedule'), FILTER_VALIDATE_BOOLEAN)) {
            return;
        }

        foreach (CategorySchedule::afterValidation() as $callback) {
            $validator->after($callback);
        }
    }

    private function category(): ?ProductCategory
    {
        $category = $this->route('category') ?? $this->route('subcategory');

        return $category instanceof ProductCategory ? $category : null;
    }
}
