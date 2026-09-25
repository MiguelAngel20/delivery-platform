<?php

namespace App\Http\Requests\Business\Catalog;

use App\Models\ProductCategory;
use App\Support\CatalogAccess;
use App\Support\CategorySchedule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreProductCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null || ! $user->can('create', ProductCategory::class)) {
            return false;
        }

        $membership = $user->activeBusinessMembership();

        if ($membership?->business === null) {
            return false;
        }

        if ($this->input('branch_id') === null || $this->input('branch_id') === '') {
            return true;
        }

        $branchId = (int) $this->input('branch_id');
        $branch = $membership->business->branches()->whereKey($branchId)->first();

        return $branch !== null
            && app(CatalogAccess::class)->canManageBranchCatalog($user, $branch);
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
        $businessId = $this->user()?->activeBusinessMembership()?->business_id;
        $branchId = $this->integer('branch_id') ?: null;
        $isPrincipal = blank($this->input('parent_id'));
        $hasSchedule = filter_var($this->input('has_schedule'), FILTER_VALIDATE_BOOLEAN);

        $rules = [
            'branch_id' => [
                'required',
                'integer',
                Rule::exists('business_branches', 'id')
                    ->where('business_id', $businessId)
                    ->whereNull('deleted_at'),
            ],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('product_categories', 'id')
                    ->where('branch_id', $branchId)
                    ->whereNull('parent_id')
                    ->whereNull('deleted_at'),
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
            'branch_id.required' => 'Selecciona una sucursal.',
            'branch_id.exists' => 'La sucursal seleccionada no es válida.',
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
            'branch_id' => 'sucursal',
            'parent_id' => 'categoría padre',
            'name' => 'nombre',
            'description' => 'descripción',
            'schedule_hours' => 'horario',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        if (! blank($this->input('parent_id'))) {
            return;
        }

        if (! filter_var($this->input('has_schedule'), FILTER_VALIDATE_BOOLEAN)) {
            return;
        }

        foreach (CategorySchedule::afterValidation() as $callback) {
            $validator->after($callback);
        }
    }
}
