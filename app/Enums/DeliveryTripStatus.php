<?php

namespace App\Enums;

enum DeliveryTripStatus: string
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Abierto',
            self::InProgress => 'En progreso',
            self::Completed => 'Completado',
            self::Cancelled => 'Cancelado',
        };
    }

    public function isActive(): bool
    {
        return in_array($this, [self::Open, self::InProgress], true);
    }
}
