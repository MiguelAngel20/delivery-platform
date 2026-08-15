<?php

namespace App\Enums;

enum CancellationReviewStatus: string
{
    case NotRequired = 'not_required';
    case Pending = 'pending';
    case Resolved = 'resolved';

    public function label(): string
    {
        return match ($this) {
            self::NotRequired => 'No requerida',
            self::Pending => 'Pendiente',
            self::Resolved => 'Resuelta',
        };
    }
}
