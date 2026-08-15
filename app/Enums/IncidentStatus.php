<?php

namespace App\Enums;

enum IncidentStatus: string
{
    case Open = 'open';
    case UnderReview = 'under_review';
    case Resolved = 'resolved';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Abierta',
            self::UnderReview => 'En revisión',
            self::Resolved => 'Resuelta',
            self::Closed => 'Cerrada',
        };
    }
}
