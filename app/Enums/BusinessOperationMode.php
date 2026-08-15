<?php

namespace App\Enums;

enum BusinessOperationMode: string
{
    case Partner = 'partner';
    case PlatformOperated = 'platform_operated';
    case Directory = 'directory';

    public function label(): string
    {
        return match ($this) {
            self::Partner => 'Empresa afiliada',
            self::PlatformOperated => 'Administrada por RIDE',
            self::Directory => 'Directorio',
        };
    }

    public function canAcceptOrders(): bool
    {
        return $this !== self::Directory;
    }

    public function isPlatformManaged(): bool
    {
        return $this === self::PlatformOperated;
    }
}
