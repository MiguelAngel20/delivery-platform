<?php

namespace App\Http\Requests\Business\Catalog;

use App\Models\ProductCategory;
use App\Support\CatalogAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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

        $branchId = (int) $this->input('branch_id');
        $branch = $membership->business->branches()->whereKey($branchId)->first();

        return $branch !== null
            && app(CatalogAccess::class)->canManageBranchCatalog($user, $branch);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $businessId = $this->user()?->activeBusinessMembership()?->business_id;

        return [
            'branch_id' => [
                'required',
                'integer',
                Rule::exists('business_branches', 'id')
                    ->where('business_id', $businessId)
                    ->whereNull('deleted_at'),
            ],
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
