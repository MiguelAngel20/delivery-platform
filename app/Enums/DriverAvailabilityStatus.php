<?php

namespace App\Enums;

enum DriverAvailabilityStatus: string
{
    case Offline = 'offline';
    case Available = 'available';
    case Busy = 'busy';
    case Paused = 'paused';

    public function label(): string
    {
        return match ($this) {
            self::Offline => 'Desconectado',
            self::Available => 'Disponible',
            self::Busy => 'En servicio',
            self::Paused => 'En pausa',
        };
    }
}
