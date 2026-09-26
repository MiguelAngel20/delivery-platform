<?php

namespace App\Support;

use App\Enums\ProductOptionGroupType;
use App\Models\Promotion;
use App\Models\PromotionItem;

final class StorefrontPromotionData
{
    /**
     * @return array<string, mixed>
     */
    public static function cartPromotion(Promotion $promotion, string $restaurantSlug): array
    {
        $promotion->loadMissing([
            'items.product.optionGroups' => fn ($query) => $query
                ->where('is_active', true)
                ->orderBy('sort_order'),
            'items.product.optionGroups.options' => fn ($query) => $query
                ->where('is_available', true)
                ->orderBy('sort_order'),
            'items.product.optionGroups.clusters' => fn ($query) => $query->orderBy('sort_order'),
            'items.product.optionGroups.clusters.options' => fn ($query) => $query
                ->where('is_available', true)
                ->orderBy('sort_order'),
        ]);

        return [
            'id' => $promotion->id,
            'restaurantSlug' => $restaurantSlug,
            'name' => $promotion->name,
            'description' => $promotion->description ?? '',
            'price' => (float) $promotion->promotion_price,
            'image_url' => $promotion->imageUrl(),
            'composition' => $promotion->items->pluck('name')->implode(' + '),
            'items' => $promotion->items
                ->map(fn (PromotionItem $item): array => self::cartPromotionItem($item))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function cartPromotionItem(PromotionItem $item): array
    {
        if (! $item->is_external_item && $item->product !== null) {
            return [
                'id' => $item->id,
                'name' => $item->name,
                'quantity' => (float) $item->quantity,
                'is_external_item' => false,
                'product_id' => $item->product_id,
                'allow_special_instructions' => $item->product->allow_special_instructions,
                'option_groups' => $item->product->optionGroups->map(function ($group): array {
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
            ];
        }

        return [
            'id' => $item->id,
            'name' => $item->name,
            'quantity' => (float) $item->quantity,
            'is_external_item' => true,
            'product_id' => null,
            'allow_special_instructions' => false,
            'option_groups' => self::externalOptionGroups(
                is_array($item->option_groups) ? $item->option_groups : [],
                $item->id,
            ),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $groups
     * @return list<array<string, mixed>>
     */
    public static function externalOptionGroups(array $groups, int $promotionItemId): array
    {
        return collect($groups)
            ->values()
            ->map(function (array $group, int $groupIndex) use ($promotionItemId): array {
                $type = ProductOptionGroupType::tryFrom((string) ($group['type'] ?? ''))
                    ?? ProductOptionGroupType::Choice;

                return [
                    'id' => self::externalGroupId($promotionItemId, $groupIndex),
                    'name' => (string) ($group['name'] ?? ''),
                    'type' => $type->value,
                    'is_required' => (bool) ($group['is_required'] ?? false),
                    'min_selection' => (int) ($group['min_selection'] ?? 0),
                    'max_selection' => (int) ($group['max_selection'] ?? 1),
                    'has_option_clusters' => (bool) ($group['has_option_clusters'] ?? false),
                    'clusters' => (function () use ($group, $promotionItemId, $groupIndex): array {
                        $flatIndex = 0;
                        $clusters = [];

                        foreach (array_values($group['clusters'] ?? []) as $clusterIndex => $cluster) {
                            if (! is_array($cluster)) {
                                continue;
                            }

                            $clusterOptions = [];

                            foreach (array_values($cluster['options'] ?? []) as $option) {
                                if (! is_array($option)) {
                                    continue;
                                }

                                $clusterOptions[] = [
                                    'id' => self::externalOptionId($promotionItemId, $groupIndex, $flatIndex),
                                    'name' => (string) ($option['name'] ?? ''),
                                    'description' => $option['description'] ?? null,
                                    'price_modifier' => (float) ($option['price_modifier'] ?? 0),
                                    'is_default' => (bool) ($option['is_default'] ?? false),
                                    'option_cluster_name' => $option['option_cluster_name']
                                        ?? ($cluster['name'] ?? null),
                                ];
                                $flatIndex++;
                            }

                            $clusters[] = [
                                'id' => self::externalGroupId($promotionItemId, $groupIndex) * 100 - ($clusterIndex + 1),
                                'name' => (string) ($cluster['name'] ?? ''),
                                'sort_order' => (int) ($cluster['sort_order'] ?? $clusterIndex),
                                'options' => $clusterOptions,
                            ];
                        }

                        return $clusters;
                    })(),
                    'options' => collect($group['options'] ?? [])
                        ->values()
                        ->map(function (array $option, int $optionIndex) use ($promotionItemId, $groupIndex): array {
                            return [
                                'id' => self::externalOptionId($promotionItemId, $groupIndex, $optionIndex),
                                'name' => (string) ($option['name'] ?? ''),
                                'description' => $option['description'] ?? null,
                                'price_modifier' => (float) ($option['price_modifier'] ?? 0),
                                'is_default' => (bool) ($option['is_default'] ?? false),
                                'option_cluster_name' => $option['option_cluster_name'] ?? null,
                            ];
                        })
                        ->all(),
                ];
            })
            ->all();
    }

    public static function externalGroupId(int $promotionItemId, int $groupIndex): int
    {
        return -($promotionItemId * 1000 + $groupIndex + 1);
    }

    public static function externalOptionId(
        int $promotionItemId,
        int $groupIndex,
        int $optionIndex,
    ): int {
        return -($promotionItemId * 100000 + $groupIndex * 1000 + $optionIndex + 1);
    }

    /**
     * @return array{promotion_item_id: int, group_index: int, option_index: int}|null
     */
    public static function decodeExternalOptionId(int $optionId): ?array
    {
        if ($optionId >= 0) {
            return null;
        }

        $encoded = abs($optionId);
        $promotionItemId = intdiv($encoded, 100000);
        $remainder = $encoded % 100000;
        $groupIndex = intdiv($remainder, 1000);
        $optionIndex = ($remainder % 1000) - 1;

        if ($promotionItemId <= 0 || $groupIndex < 0 || $optionIndex < 0) {
            return null;
        }

        return [
            'promotion_item_id' => $promotionItemId,
            'group_index' => $groupIndex,
            'option_index' => $optionIndex,
        ];
    }
}
