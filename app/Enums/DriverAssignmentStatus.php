<?php

namespace App\Enums;

enum DriverAssignmentStatus: string
{
    case Offered = 'offered';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Offered => 'Ofertado',
            self::Accepted => 'Aceptado',
            self::Rejected => 'Rechazado',
            self::Expired => 'Expirado',
            self::Cancelled => 'Cancelado',
        };
    }
}
