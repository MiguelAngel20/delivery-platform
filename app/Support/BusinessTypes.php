<?php

namespace App\Support;

final class BusinessTypes
{
    /**
     * @return list<string>
     */
    public static function options(): array
    {
        return [
            'Restaurante',
            'Comida rápida',
            'Postres',
            'Cafetería',
            'Farmacia',
            'Tienda',
            'Otro',
        ];
    }
}
