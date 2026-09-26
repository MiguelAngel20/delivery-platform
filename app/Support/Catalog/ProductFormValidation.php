<?php

namespace App\Support\Catalog;

use App\Enums\ProductOptionGroupType;

trait ProductFormValidation
{
    protected function sanitizeProductOptionGroups(): void
    {
        if (! is_array($this->input('option_groups'))) {
            return;
        }

        $groups = collect($this->input('option_groups'))
            ->map(function (array $group): array {
                $type = ProductOptionGroupType::tryFrom((string) ($group['type'] ?? ''));
                $hasClusters = $type === ProductOptionGroupType::Choice
                    && filter_var($group['has_option_clusters'] ?? false, FILTER_VALIDATE_BOOLEAN);

                $group['has_option_clusters'] = $hasClusters;

                if ($hasClusters) {
                    $clusters = collect($group['clusters'] ?? [])
                        ->filter(fn (mixed $cluster): bool => is_array($cluster))
                        ->map(function (array $cluster): array {
                            $options = collect($cluster['options'] ?? [])
                                ->filter(fn (mixed $option): bool => is_array($option))
                                ->filter(fn (array $option): bool => filled(trim((string) ($option['name'] ?? ''))))
                                ->values()
                                ->all();

                            return [
                                ...$cluster,
                                'name' => trim((string) ($cluster['name'] ?? '')),
                                'options' => $options,
                            ];
                        })
                        ->filter(fn (array $cluster): bool => $cluster['options'] !== [])
                        ->values()
                        ->all();

                    $group['clusters'] = $clusters;
                    $group['options'] = collect($clusters)
                        ->flatMap(fn (array $cluster): array => $cluster['options'])
                        ->values()
                        ->all();

                    return $group;
                }

                $group['clusters'] = [];
                $group['options'] = collect($group['options'] ?? [])
                    ->filter(fn (array $option): bool => filled(trim((string) ($option['name'] ?? ''))))
                    ->values()
                    ->all();

                return $group;
            })
            ->values()
            ->all();

        $this->merge(['option_groups' => $groups]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function optionGroupClusterRules(): array
    {
        return [
            'option_groups.*.has_option_clusters' => ['sometimes', 'boolean'],
            'option_groups.*.clusters' => ['nullable', 'array'],
            'option_groups.*.clusters.*.name' => ['required_with:option_groups.*.clusters', 'string', 'max:100'],
            'option_groups.*.clusters.*.options' => ['required_with:option_groups.*.clusters', 'array', 'min:1'],
            'option_groups.*.clusters.*.options.*.name' => ['required', 'string', 'max:100'],
            'option_groups.*.clusters.*.options.*.description' => ['nullable', 'string'],
            'option_groups.*.clusters.*.options.*.price_modifier' => ['nullable', 'numeric'],
            'option_groups.*.clusters.*.options.*.is_default' => ['sometimes', 'boolean'],
            'option_groups.*.clusters.*.options.*.is_available' => ['sometimes', 'boolean'],
            'option_groups.*.clusters.*.options.*.sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'branch_id.required' => 'Selecciona una sucursal.',
            'branch_id.exists' => 'La sucursal seleccionada no es válida.',
            'product_category_id.exists' => 'La categoría seleccionada no pertenece a la sucursal.',
            'name.required' => 'El nombre del producto es obligatorio.',
            'name.max' => 'El nombre no puede superar :max caracteres.',
            'list_price.required' => 'El precio de lista es obligatorio.',
            'list_price.numeric' => 'Ingresa un precio de lista válido.',
            'list_price.min' => 'El precio de lista debe ser mayor o igual a :min.',
            'image.image' => 'La imagen debe ser un archivo válido.',
            'image.mimes' => 'La imagen debe ser JPG, PNG o WebP.',
            'image.max' => 'La imagen no puede superar :max kilobytes.',
            'existing_image_path' => 'La imagen seleccionada no es válida.',
            'option_groups.*.options' => 'Agrega al menos una opción con nombre en cada sección activa.',
            'option_groups.*.options.*.name.required' => 'Cada opción debe tener un nombre.',
            'option_groups.*.options.*.name.max' => 'El nombre de la opción no puede superar :max caracteres.',
            'option_groups.*.max_selection.gte' => 'El máximo de selección no puede ser menor que el mínimo.',
            'option_groups.*.options.*.price_modifier.numeric' => 'Ingresa un precio adicional válido.',
            'option_groups.*.clusters' => 'Agrega al menos una agrupación con variantes.',
            'option_groups.*.clusters.*.name.required' => 'Cada agrupación debe tener un nombre.',
            'option_groups.*.clusters.*.options' => 'Cada agrupación debe tener al menos una variante.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'branch_id' => 'sucursal',
            'product_category_id' => 'categoría',
            'name' => 'nombre',
            'description' => 'descripción',
            'list_price' => 'precio de lista',
            'image' => 'imagen',
            'existing_image_path' => 'imagen existente',
            'option_groups' => 'personalización',
            'option_groups.*.name' => 'nombre del grupo',
            'option_groups.*.min_selection' => 'mínimo de selección',
            'option_groups.*.max_selection' => 'máximo de selección',
            'option_groups.*.options.*.name' => 'nombre de la opción',
            'option_groups.*.options.*.price_modifier' => 'precio adicional',
            'option_groups.*.clusters.*.name' => 'nombre de la agrupación',
        ];
    }
}
