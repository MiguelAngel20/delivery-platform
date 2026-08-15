<?php

namespace App\Enums;

enum ProductOptionGroupType: string
{
    case Removable = 'removable';
    case Addon = 'addon';
    case Choice = 'choice';

    public function label(): string
    {
        return match ($this) {
            self::Removable => 'Removable',
            self::Addon => 'Addon',
            self::Choice => 'Choice',
        };
    }

    public function displayLabel(): string
    {
        return match ($this) {
            self::Removable => 'Ingredientes removibles',
            self::Addon => 'Extras',
            self::Choice => 'Elección',
        };
    }
}
