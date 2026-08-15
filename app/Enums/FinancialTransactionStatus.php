<?php

namespace App\Enums;

enum FinancialTransactionStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Voided = 'voided';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente',
            self::Completed => 'Completado',
            self::Voided => 'Anulado',
        };
    }
}
