<?php

namespace App\Enums;

enum ProductOptionGroupType: string
{
    case Removable = 'removable';
    case Addon = 'addon';
    case Choice = 'choice';
    case Size = 'size';

    public function label(): string
    {
        return match ($this) {
            self::Removable => 'Quitar ingredientes',
            self::Addon => 'Extras',
            self::Choice => 'Variantes',
            self::Size => 'Tamaños / porciones',
        };
    }

    public function displayLabel(): string
    {
        return match ($this) {
            self::Removable => 'Quitar ingredientes',
            self::Addon => 'Extras',
            self::Choice => 'Variantes',
            self::Size => 'Tamaños / porciones',
        };
    }
}
