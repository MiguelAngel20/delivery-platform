<?php

namespace App\Enums;

enum CustomerTrustLevel: string
{
    case New = 'new';
    case Good = 'good';
    case Trusted = 'trusted';
    case Restricted = 'restricted';
    case Blocked = 'blocked';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Nuevo',
            self::Good => 'Bueno',
            self::Trusted => 'Confiable',
            self::Restricted => 'Restringido',
            self::Blocked => 'Bloqueado',
        };
    }

    public function publicLabel(): string
    {
        return match ($this) {
            self::New => 'Cuenta nueva',
            self::Good, self::Trusted, self::Restricted, self::Blocked => 'Cuenta verificada',
        };
    }

    public function isFrequent(): bool
    {
        return $this === self::Trusted;
    }

    public function isRestricted(): bool
    {
        return $this === self::Restricted;
    }

    public function isBlocked(): bool
    {
        return $this === self::Blocked;
    }

    public function tone(): string
    {
        return match ($this) {
            self::New => 'info',
            self::Good => 'success',
            self::Trusted => 'primary',
            self::Restricted => 'warning',
            self::Blocked => 'danger',
        };
    }
}
