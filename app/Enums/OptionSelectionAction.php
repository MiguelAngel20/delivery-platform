<?php

namespace App\Enums;

enum OptionSelectionAction: string
{
    case Removed = 'removed';
    case Added = 'added';
    case Selected = 'selected';

    public function label(): string
    {
        return match ($this) {
            self::Removed => 'Removido',
            self::Added => 'Agregado',
            self::Selected => 'Seleccionado',
        };
    }
}
