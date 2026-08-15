<?php

namespace App\Enums;

enum CustomOrderRequestStatus: string
{
    case PendingReview = 'pending_review';
    case Reviewing = 'reviewing';
    case Quoted = 'quoted';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
    case ConvertedToOrder = 'converted_to_order';

    public function label(): string
    {
        return match ($this) {
            self::PendingReview => 'Pendiente de revisión',
            self::Reviewing => 'En revisión',
            self::Quoted => 'Cotizada',
            self::Accepted => 'Aceptada',
            self::Rejected => 'Rechazada',
            self::Cancelled => 'Cancelada',
            self::Expired => 'Expirada',
            self::ConvertedToOrder => 'Convertida a pedido',
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [
            self::PendingReview,
            self::Reviewing,
            self::Quoted,
        ], true);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [
            self::Rejected,
            self::Cancelled,
            self::Expired,
            self::ConvertedToOrder,
        ], true);
    }
}
