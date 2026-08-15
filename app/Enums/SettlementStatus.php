<?php

namespace App\Enums;

enum SettlementStatus: string
{
    case Open = 'open';
    case Settled = 'settled';
    case PartiallySettled = 'partially_settled';
    case RequiresReview = 'requires_review';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Abierto',
            self::Settled => 'Conciliado',
            self::PartiallySettled => 'Parcial',
            self::RequiresReview => 'Requiere revisión',
        };
    }
}
