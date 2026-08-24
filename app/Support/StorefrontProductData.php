<?php

namespace App\Support;

use App\Models\Product;

final class StorefrontProductData
{
    /**
     * @return array<string, mixed>
     */
    public static function menuProduct(Product $product, string $restaurantSlug): array
    {
        $product->loadMissing([
            'category:id,name,parent_id',
            'category.parent:id,name',
            'currentPrice',
            'optionGroups.options',
        ]);

        $category = $product->category;
        $parentName = $category?->parent?->name;
        $categoryName = $category?->name;

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
            'image_url' => $product->imageUrl(),
            'is_available' => $product->is_available,
            'allow_special_instructions' => $product->allow_special_instructions,
            'option_groups' => $product->optionGroups->map(fn ($group): array => [
                'id' => $group->id,
                'name' => $group->name,
                'type' => $group->type->value,
                'is_required' => $group->is_required,
                'min_selection' => $group->min_selection,
                'max_selection' => $group->max_selection,
                'options' => $group->options->map(fn ($option): array => [
                    'id' => $option->id,
                    'name' => $option->name,
                    'description' => $option->description,
                    'price_modifier' => (float) $option->price_modifier,
                    'is_default' => $option->is_default,
                ])->values()->all(),
            ])->values()->all(),
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
