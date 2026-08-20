<?php

namespace App\Support\Catalog;

trait ProductFormValidation
{
    protected function sanitizeProductOptionGroups(): void
    {
        if (! is_array($this->input('option_groups'))) {
            return;
        }

        $groups = collect($this->input('option_groups'))
            ->map(function (array $group): array {
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
            'option_groups.*.options' => 'Agrega al menos una opción con nombre en cada sección activa.',
            'option_groups.*.options.*.name.required' => 'Cada opción debe tener un nombre.',
            'option_groups.*.options.*.name.max' => 'El nombre de la opción no puede superar :max caracteres.',
            'option_groups.*.max_selection.gte' => 'El máximo de selección no puede ser menor que el mínimo.',
            'option_groups.*.options.*.price_modifier.numeric' => 'Ingresa un precio adicional válido.',
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
            'option_groups' => 'personalización',
            'option_groups.*.name' => 'nombre del grupo',
            'option_groups.*.min_selection' => 'mínimo de selección',
            'option_groups.*.max_selection' => 'máximo de selección',
            'option_groups.*.options.*.name' => 'nombre de la opción',
            'option_groups.*.options.*.price_modifier' => 'precio adicional',
        ];
    }
}
