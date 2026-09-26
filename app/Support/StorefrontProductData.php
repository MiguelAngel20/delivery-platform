<?php

namespace App\Support;

use App\Enums\ProductOptionGroupType;
use App\Models\Product;

final class StorefrontProductData
{
    /**
     * @return array<string, mixed>
     */
    public static function menuProduct(
        Product $product,
        string $restaurantSlug,
        ?string $fallbackImageUrl = null,
    ): array {
        $product->loadMissing([
            'category:id,name,parent_id',
            'category.parent:id,name',
            'currentPrice',
            'optionGroups.options',
            'optionGroups.clusters.options',
        ]);

        $category = $product->category;
        $parentName = $category?->parent?->name;
        $categoryName = $category?->name;
        $hasSizeOptions = $product->optionGroups->contains(
            fn ($group): bool => $group->type === ProductOptionGroupType::Size && $group->is_active,
        );

        return [
            'id' => $product->id,
            'restaurantSlug' => $restaurantSlug,
            'category' => $category?->displayPath() ?? 'Sin categoría',
            'subcategory' => $parentName !== null ? $categoryName : null,
            'category_path' => $category?->displayPath() ?? 'Sin categoría',
            'product_category_id' => $product->product_category_id,
            'parent_category_id' => $category?->parent_id,
            'name' => $product->name,
            'description' => $product->description ?? '',
            'price' => (float) ($product->currentPrice?->list_price ?? 0),
            'has_size_options' => $hasSizeOptions,
            'image_url' => $product->imageUrl() ?? $fallbackImageUrl,
            'is_available' => $product->is_available,
            'allow_special_instructions' => $product->allow_special_instructions,
            'option_groups' => $product->optionGroups->map(function ($group): array {
                $mapOption = fn ($option): array => [
                    'id' => $option->id,
                    'name' => $option->name,
                    'description' => $option->description,
                    'price_modifier' => (float) $option->price_modifier,
                    'is_default' => $option->is_default,
                    'option_cluster_id' => $option->option_cluster_id,
                ];

                $clusters = $group->has_option_clusters
                    ? $group->clusters->map(fn ($cluster): array => [
                        'id' => $cluster->id,
                        'name' => $cluster->name,
                        'sort_order' => $cluster->sort_order,
                        'options' => $cluster->options->map($mapOption)->values()->all(),
                    ])->values()->all()
                    : [];

                return [
                    'id' => $group->id,
                    'name' => $group->name,
                    'type' => $group->type->value,
                    'is_required' => $group->is_required,
                    'min_selection' => $group->min_selection,
                    'max_selection' => $group->max_selection,
                    'has_option_clusters' => $group->has_option_clusters,
                    'clusters' => $clusters,
                    'options' => $group->options->map($mapOption)->values()->all(),
                ];
            })->values()->all(),
            'ingredients' => $product->optionGroups
                ->filter(fn ($group) => $group->type->value === 'removable')
                ->flatMap(fn ($group) => $group->options->where('is_default', true)->pluck('name'))
                ->values()
                ->all(),
            'extras' => $product->optionGroups
                ->filter(fn ($group) => $group->type->value === 'addon')
                ->flatMap(fn ($group) => $group->options->map(fn ($option): array => [
                    'id' => (string) $option->id,
                    'name' => $option->name,
                    'price' => (float) $option->price_modifier,
                    'option_id' => $option->id,
                    'group_id' => $group->id,
                ]))
                ->values()
                ->all(),
        ];
    }
}
