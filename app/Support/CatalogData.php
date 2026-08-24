<?php

namespace App\Support;

use App\Enums\ProductOptionGroupType;
use App\Enums\PromotionStatus;
use App\Models\Business;
use App\Models\BusinessBranch;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Promotion;

final class CatalogData
{
    /**
     * @return array<string, mixed>
     */
    public static function category(ProductCategory $category): array
    {
        $category->loadMissing(['branch:id,name', 'parent:id,name']);

        return [
            'id' => $category->id,
            'branch_id' => $category->branch_id,
            'branch_name' => $category->branch?->name,
            'parent_id' => $category->parent_id,
            'parent_name' => $category->parent?->name,
            'name' => $category->name,
            'display_name' => $category->displayPath(),
            'description' => $category->description,
            'sort_order' => $category->sort_order,
            'is_active' => $category->is_active,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function product(Product $product): array
    {
        $product->loadMissing([
            'category:id,name,parent_id',
            'category.parent:id,name',
            'currentPrice',
            'optionGroups.options',
            'branch:id,name',
        ]);

        return [
            'id' => $product->id,
            'branch_id' => $product->branch_id,
            'branch_name' => $product->branch?->name,
            'product_category_id' => $product->product_category_id,
            'principal_category_id' => $product->category?->parent_id ?? $product->product_category_id,
            'subcategory_id' => $product->category?->parent_id !== null
                ? $product->product_category_id
                : null,
            'category_name' => $product->category?->displayPath(),
            'name' => $product->name,
            'description' => $product->description,
            'image_url' => $product->imageUrl(),
            'is_available' => $product->is_available,
            'is_active' => $product->is_active,
            'allow_special_instructions' => $product->allow_special_instructions,
            'list_price' => $product->currentPrice?->list_price !== null
                ? (string) $product->currentPrice->list_price
                : null,
            'acquisition_cost' => $product->currentPrice?->acquisition_cost !== null
                ? (string) $product->currentPrice->acquisition_cost
                : null,
            'option_groups' => $product->optionGroups->map(fn ($group): array => [
                'id' => $group->id,
                'name' => $group->name,
                'type' => $group->type->value,
                'type_label' => $group->type->displayLabel(),
                'is_required' => $group->is_required,
                'min_selection' => $group->min_selection,
                'max_selection' => $group->max_selection,
                'sort_order' => $group->sort_order,
                'is_active' => $group->is_active,
                'options' => $group->options->map(fn ($option): array => [
                    'id' => $option->id,
                    'name' => $option->name,
                    'description' => $option->description,
                    'price_modifier' => (string) $option->price_modifier,
                    'is_default' => $option->is_default,
                    'is_available' => $option->is_available,
                    'sort_order' => $option->sort_order,
                ])->values()->all(),
            ])->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function productListRow(Product $product): array
    {
        $product->loadMissing([
            'category:id,name,parent_id',
            'category.parent:id,name',
            'currentPrice',
            'branch:id,name',
        ]);

        return [
            'id' => $product->id,
            'branch_id' => $product->branch_id,
            'branch_name' => $product->branch?->name,
            'name' => $product->name,
            'category_name' => $product->category?->displayPath() ?? '—',
            'list_price' => $product->currentPrice?->list_price !== null
                ? (string) $product->currentPrice->list_price
                : null,
            'is_available' => $product->is_available,
            'is_active' => $product->is_active,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function promotion(Promotion $promotion): array
    {
        $promotion->loadMissing(['items.product:id,name', 'branch:id,name']);

        return [
            'id' => $promotion->id,
            'branch_id' => $promotion->branch_id,
            'branch_name' => $promotion->branch?->name,
            'name' => $promotion->name,
            'description' => $promotion->description,
            'promotion_price' => (string) $promotion->promotion_price,
            'image_url' => $promotion->imageUrl(),
            'starts_at' => $promotion->starts_at?->toIso8601String(),
            'ends_at' => $promotion->ends_at?->toIso8601String(),
            'status' => $promotion->status->value,
            'status_label' => $promotion->status->label(),
            'items' => $promotion->items->map(fn ($item): array => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'name' => $item->name,
                'description' => $item->description,
                'quantity' => (string) $item->quantity,
                'original_price' => $item->original_price !== null ? (string) $item->original_price : null,
                'is_external_item' => $item->is_external_item,
            ])->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function formOptions(Business $business): array
    {
        $branches = $business->branches()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (BusinessBranch $branch): array => [
                'value' => (string) $branch->id,
                'label' => $branch->name,
            ])
            ->values()
            ->all();

        $categories = ProductCategory::query()
            ->whereIn('branch_id', $business->branches()->select('id'))
            ->where('is_active', true)
            ->with('parent:id,name')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'branch_id', 'parent_id', 'name'])
            ->sortBy(fn (ProductCategory $category): string => sprintf(
                '%d-%s-%s',
                $category->branch_id,
                $category->parent?->name ?? $category->name,
                $category->parent_id === null ? '0' : '1'.$category->name,
            ))
            ->values()
            ->map(fn (ProductCategory $category): array => [
                'value' => (string) $category->id,
                'label' => $category->displayPath(),
                'branch_id' => $category->branch_id,
                'parent_id' => $category->parent_id,
                'is_root' => $category->parent_id === null,
            ])
            ->all();

        $parentCategories = ProductCategory::query()
            ->whereIn('branch_id', $business->branches()->select('id'))
            ->where('is_active', true)
            ->roots()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'branch_id', 'name'])
            ->map(fn (ProductCategory $category): array => [
                'value' => (string) $category->id,
                'label' => $category->name,
                'branch_id' => $category->branch_id,
            ])
            ->values()
            ->all();

        $products = Product::query()
            ->whereIn('branch_id', $business->branches()->select('id'))
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'branch_id', 'name'])
            ->map(fn (Product $product): array => [
                'value' => (string) $product->id,
                'label' => $product->name,
                'branch_id' => $product->branch_id,
            ])
            ->values()
            ->all();

        return [
            'branches' => $branches,
            'categories' => $categories,
            'parent_categories' => $parentCategories,
            'products' => $products,
            'option_group_types' => collect(ProductOptionGroupType::cases())
                ->map(fn (ProductOptionGroupType $type): array => [
                    'value' => $type->value,
                    'label' => $type->displayLabel(),
                ])
                ->values()
                ->all(),
            'promotion_statuses' => collect(PromotionStatus::cases())
                ->map(fn (PromotionStatus $status): array => [
                    'value' => $status->value,
                    'label' => $status->label(),
                ])
                ->values()
                ->all(),
        ];
    }
}
