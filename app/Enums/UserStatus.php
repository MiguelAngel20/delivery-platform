<?php

namespace App\Enums;

enum UserStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Inactive = 'inactive';

    public function canAuthenticate(): bool
    {
        return $this === self::Active;
    }

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Activo',
            self::Suspended => 'Suspendido',
            self::Inactive => 'Inactivo',
        };
    }
}
