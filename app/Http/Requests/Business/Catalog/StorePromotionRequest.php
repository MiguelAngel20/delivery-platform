<?php

namespace App\Http\Requests\Business\Catalog;

use App\Enums\ProductOptionGroupType;
use App\Enums\PromotionStatus;
use App\Models\Promotion;
use App\Support\Catalog\PromotionFormValidation;
use App\Support\CatalogAccess;
use App\Support\PromotionSchedule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePromotionRequest extends FormRequest
{
    use PromotionFormValidation;

    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null || ! $user->can('create', Promotion::class)) {
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
        if (is_string($this->input('items'))) {
            $decoded = json_decode($this->input('items'), true);
            $this->merge([
                'items' => is_array($decoded) ? $decoded : [],
            ]);
        }

        if ($this->has('recurring_hours')) {
            $this->merge([
                'recurring_hours' => PromotionSchedule::prepareInput($this->input('recurring_hours')),
            ]);
        }

        $this->merge([
            'is_recurring' => filter_var($this->input('is_recurring'), FILTER_VALIDATE_BOOLEAN),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $businessId = $this->user()?->activeBusinessMembership()?->business_id;
        $branchId = $this->input('branch_id');
        $isRecurring = (bool) $this->input('is_recurring');

        $rules = [
            'branch_id' => [
                'required',
                'integer',
                Rule::exists('business_branches', 'id')
                    ->where('business_id', $businessId)
                    ->whereNull('deleted_at'),
            ],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'promotion_price' => ['required', 'numeric', 'min:0'],
            'is_recurring' => ['required', 'boolean'],
            'status' => ['required', Rule::enum(PromotionStatus::class)],
            'items' => ['nullable', 'array'],
            'items.*.is_external_item' => ['required_with:items', 'boolean'],
            'items.*.product_id' => [
                'nullable',
                'integer',
                Rule::exists('products', 'id')
                    ->where('branch_id', $branchId)
                    ->whereNull('deleted_at'),
            ],
            'items.*.name' => ['nullable', 'string', 'max:150'],
            'items.*.description' => ['nullable', 'string'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.original_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.option_groups' => ['nullable', 'array'],
            'items.*.option_groups.*.name' => ['required_with:items.*.option_groups', 'string', 'max:100'],
            'items.*.option_groups.*.type' => ['required_with:items.*.option_groups', Rule::enum(ProductOptionGroupType::class)],
            'items.*.option_groups.*.is_required' => ['sometimes', 'boolean'],
            'items.*.option_groups.*.min_selection' => ['required_with:items.*.option_groups', 'integer', 'min:0'],
            'items.*.option_groups.*.max_selection' => ['required_with:items.*.option_groups', 'integer', 'gte:items.*.option_groups.*.min_selection'],
            'items.*.option_groups.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'items.*.option_groups.*.is_active' => ['sometimes', 'boolean'],
            'items.*.option_groups.*.options' => ['required', 'array', 'min:1'],
            'items.*.option_groups.*.options.*.name' => ['required', 'string', 'max:100'],
            'items.*.option_groups.*.options.*.description' => ['nullable', 'string'],
            'items.*.option_groups.*.options.*.price_modifier' => ['nullable', 'numeric'],
            'items.*.option_groups.*.options.*.is_default' => ['sometimes', 'boolean'],
            'items.*.option_groups.*.options.*.is_available' => ['sometimes', 'boolean'],
            'items.*.option_groups.*.options.*.sort_order' => ['nullable', 'integer', 'min:0'],
        ];

        if ($isRecurring) {
            $rules['starts_at'] = ['nullable'];
            $rules['ends_at'] = ['nullable'];
            $rules['recurrence_starts_on'] = ['required', 'date'];
            $rules['recurrence_ends_on'] = ['nullable', 'date', 'after_or_equal:recurrence_starts_on'];
            $rules = [...$rules, ...PromotionSchedule::validationRules(required: true)];
        } else {
            $rules['starts_at'] = ['nullable', 'date'];
            $rules['ends_at'] = ['nullable', 'date', 'after_or_equal:starts_at'];
            $rules['recurrence_starts_on'] = ['nullable'];
            $rules['recurrence_ends_on'] = ['nullable'];
            $rules['recurring_hours'] = ['nullable'];
        }

        return $rules;
    }

    public function withValidator($validator): void
    {
        $this->appendPromotionItemRules($validator);

        foreach (PromotionSchedule::afterValidation() as $callback) {
            $validator->after($callback);
        }
    }
}
