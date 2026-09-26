<?php

namespace App\Support\Catalog;

use App\Enums\ProductOptionGroupType;

final class PromotionItemOptionGroups
{
    /**
     * @param  list<array<string, mixed>>|null  $groups
     * @return list<array<string, mixed>>|null
     */
    public static function sanitize(?array $groups): ?array
    {
        if ($groups === null || $groups === []) {
            return null;
        }

        $sanitized = collect($groups)
            ->map(function (array $group): array {
                $type = ProductOptionGroupType::tryFrom((string) ($group['type'] ?? ''));
                $hasClusters = $type === ProductOptionGroupType::Choice
                    && filter_var($group['has_option_clusters'] ?? false, FILTER_VALIDATE_BOOLEAN);

                if ($hasClusters) {
                    $clusters = collect($group['clusters'] ?? [])
                        ->filter(fn (mixed $cluster): bool => is_array($cluster))
                        ->map(function (array $cluster, int $clusterIndex): array {
                            $options = collect($cluster['options'] ?? [])
                                ->filter(fn (mixed $option): bool => is_array($option))
                                ->filter(fn (array $option): bool => filled($option['name'] ?? null))
                                ->values()
                                ->map(fn (array $option, int $index): array => [
                                    'name' => (string) $option['name'],
                                    'description' => $option['description'] ?? null,
                                    'price_modifier' => $option['price_modifier'] ?? 0,
                                    'is_default' => (bool) ($option['is_default'] ?? false),
                                    'is_available' => (bool) ($option['is_available'] ?? true),
                                    'sort_order' => (int) ($option['sort_order'] ?? $index),
                                    'option_cluster_name' => trim((string) ($cluster['name'] ?? '')),
                                ])
                                ->all();

                            return [
                                'name' => trim((string) ($cluster['name'] ?? '')),
                                'sort_order' => (int) ($cluster['sort_order'] ?? $clusterIndex),
                                'options' => $options,
                            ];
                        })
                        ->filter(fn (array $cluster): bool => $cluster['name'] !== '' && $cluster['options'] !== [])
                        ->values()
                        ->all();

                    return [
                        'name' => (string) ($group['name'] ?? ''),
                        'type' => (string) ($group['type'] ?? ''),
                        'is_required' => (bool) ($group['is_required'] ?? false),
                        'min_selection' => (int) ($group['min_selection'] ?? 0),
                        'max_selection' => (int) ($group['max_selection'] ?? 1),
                        'sort_order' => (int) ($group['sort_order'] ?? 0),
                        'is_active' => (bool) ($group['is_active'] ?? true),
                        'has_option_clusters' => true,
                        'clusters' => $clusters,
                        'options' => collect($clusters)
                            ->flatMap(fn (array $cluster): array => $cluster['options'])
                            ->values()
                            ->all(),
                    ];
                }

                $options = collect($group['options'] ?? [])
                    ->filter(fn (array $option): bool => filled($option['name'] ?? null))
                    ->values()
                    ->map(fn (array $option, int $index): array => [
                        'name' => (string) $option['name'],
                        'description' => $option['description'] ?? null,
                        'price_modifier' => $option['price_modifier'] ?? 0,
                        'is_default' => (bool) ($option['is_default'] ?? false),
                        'is_available' => (bool) ($option['is_available'] ?? true),
                        'sort_order' => (int) ($option['sort_order'] ?? $index),
                        'option_cluster_name' => null,
                    ])
                    ->all();

                return [
                    'name' => (string) ($group['name'] ?? ''),
                    'type' => (string) ($group['type'] ?? ''),
                    'is_required' => (bool) ($group['is_required'] ?? false),
                    'min_selection' => (int) ($group['min_selection'] ?? 0),
                    'max_selection' => (int) ($group['max_selection'] ?? 1),
                    'sort_order' => (int) ($group['sort_order'] ?? 0),
                    'is_active' => (bool) ($group['is_active'] ?? true),
                    'has_option_clusters' => false,
                    'clusters' => [],
                    'options' => $options,
                ];
            })
            ->filter(fn (array $group): bool => $group['options'] !== [])
            ->values()
            ->all();

        return $sanitized === [] ? null : $sanitized;
    }

    /**
     * @param  list<array<string, mixed>>|null  $groups
     * @return array<string, string>
     */
    public static function validationErrors(?array $groups, string $prefix): array
    {
        if ($groups === null || $groups === []) {
            return [];
        }

        $errors = [];

        foreach ($groups as $groupIndex => $group) {
            $hasClusters = filter_var($group['has_option_clusters'] ?? false, FILTER_VALIDATE_BOOLEAN);

            if ($hasClusters) {
                $clusters = collect($group['clusters'] ?? [])
                    ->filter(fn (mixed $cluster): bool => is_array($cluster));

                if ($clusters->isEmpty()) {
                    $errors["{$prefix}.{$groupIndex}.clusters"] = 'Agrega al menos una agrupación con variantes.';
                }

                foreach ($clusters as $clusterIndex => $cluster) {
                    if (! filled(trim((string) ($cluster['name'] ?? '')))) {
                        $errors["{$prefix}.{$groupIndex}.clusters.{$clusterIndex}.name"] = 'Cada agrupación debe tener un nombre.';
                    }

                    $namedOptions = collect($cluster['options'] ?? [])
                        ->filter(fn (mixed $option): bool => is_array($option))
                        ->filter(fn (array $option): bool => filled($option['name'] ?? null));

                    if ($namedOptions->isEmpty()) {
                        $errors["{$prefix}.{$groupIndex}.clusters.{$clusterIndex}.options"] = 'Cada agrupación debe tener al menos una variante.';
                    }
                }
            } else {
                $namedOptions = collect($group['options'] ?? [])
                    ->filter(fn (array $option): bool => filled($option['name'] ?? null));

                if ($namedOptions->isEmpty()) {
                    $errors["{$prefix}.{$groupIndex}.options"] = 'Agrega al menos una opción con nombre.';
                }
            }

            $min = (int) ($group['min_selection'] ?? 0);
            $max = (int) ($group['max_selection'] ?? 1);

            if ($min < 0 || $max < $min) {
                $errors["{$prefix}.{$groupIndex}.max_selection"] = 'El máximo no puede ser menor que el mínimo.';
            }

            $type = ProductOptionGroupType::tryFrom((string) ($group['type'] ?? ''));

            if ($type === ProductOptionGroupType::Removable && ($group['is_required'] ?? false)) {
                $errors["{$prefix}.{$groupIndex}.is_required"] = 'Los grupos de quitar ingredientes no deben ser obligatorios.';
            }
        }

        return $errors;
    }
}
