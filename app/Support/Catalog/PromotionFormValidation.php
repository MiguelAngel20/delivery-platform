<?php

namespace App\Support\Catalog;

use Illuminate\Contracts\Validation\Validator;

trait PromotionFormValidation
{
    protected function appendPromotionItemRules(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach ($this->input('items', []) as $index => $item) {
                $isExternal = (bool) ($item['is_external_item'] ?? false);

                if ($isExternal) {
                    if (blank($item['name'] ?? null)) {
                        $validator->errors()->add("items.{$index}.name", 'El ítem externo requiere nombre.');
                    }

                    if (! blank($item['product_id'] ?? null)) {
                        $validator->errors()->add("items.{$index}.product_id", 'Un ítem externo no debe tener producto del menú.');
                    }

                    continue;
                }

                if (blank($item['product_id'] ?? null)) {
                    $validator->errors()->add("items.{$index}.product_id", 'Debes seleccionar un producto del menú.');
                }
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'branch_id.required' => 'Selecciona una sucursal.',
            'branch_id.exists' => 'La sucursal seleccionada no es válida.',
            'name.required' => 'El nombre de la promoción es obligatorio.',
            'name.max' => 'El nombre no puede superar :max caracteres.',
            'promotion_price.required' => 'El precio promocional es obligatorio.',
            'promotion_price.numeric' => 'Ingresa un precio promocional válido.',
            'promotion_price.min' => 'El precio promocional debe ser mayor o igual a :min.',
            'status.required' => 'Selecciona un estado.',
            'starts_at.date' => 'La fecha de inicio no es válida.',
            'ends_at.date' => 'La fecha de fin no es válida.',
            'ends_at.after_or_equal' => 'La fecha de fin debe ser posterior o igual al inicio.',
            'image.image' => 'La imagen debe ser un archivo válido.',
            'image.mimes' => 'La imagen debe ser JPG, PNG o WebP.',
            'image.max' => 'La imagen no puede superar :max kilobytes.',
            'items.required' => 'Agrega al menos un ítem a la promoción.',
            'items.min' => 'Agrega al menos un ítem a la promoción.',
            'items.*.quantity.required' => 'La cantidad del ítem es obligatoria.',
            'items.*.quantity.numeric' => 'Ingresa una cantidad válida.',
            'items.*.quantity.min' => 'La cantidad debe ser mayor a 0.',
            'items.*.name.max' => 'El nombre del ítem no puede superar :max caracteres.',
            'items.*.product_id.exists' => 'El producto seleccionado no pertenece a la sucursal.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'branch_id' => 'sucursal',
            'name' => 'nombre',
            'description' => 'descripción',
            'promotion_price' => 'precio promocional',
            'status' => 'estado',
            'starts_at' => 'fecha de inicio',
            'ends_at' => 'fecha de fin',
            'image' => 'imagen',
            'items' => 'ítems',
            'items.*.name' => 'nombre del ítem',
            'items.*.product_id' => 'producto',
            'items.*.quantity' => 'cantidad',
        ];
    }
}
