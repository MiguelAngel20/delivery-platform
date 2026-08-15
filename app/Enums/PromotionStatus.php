<?php

namespace App\Enums;

enum PromotionStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Paused = 'paused';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Borrador',
            self::Active => 'Activa',
            self::Paused => 'Pausada',
            self::Expired => 'Expirada',
        };
    }
}
