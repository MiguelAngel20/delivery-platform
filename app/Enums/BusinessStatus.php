<?php

namespace App\Enums;

enum BusinessStatus: string
{
    case PendingApproval = 'pending_approval';
    case Active = 'active';
    case Rejected = 'rejected';
    case Suspended = 'suspended';
    case Inactive = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::PendingApproval => 'Pendiente',
            self::Active => 'Activa',
            self::Rejected => 'Rechazada',
            self::Suspended => 'Suspendida',
            self::Inactive => 'Inactiva',
        };
    }
}
