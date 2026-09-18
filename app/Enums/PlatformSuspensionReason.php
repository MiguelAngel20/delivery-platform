<?php

namespace App\Enums;

enum PlatformSuspensionReason: string
{
    case Maintenance = 'maintenance';
    case Rain = 'rain';
    case OffHours = 'off_hours';

    public function label(): string
    {
        return match ($this) {
            self::Maintenance => 'Mantenimiento del sistema',
            self::Rain => 'Lluvia intensa',
            self::OffHours => 'Fuera de horario',
        };
    }

    public function title(): string
    {
        return match ($this) {
            self::Maintenance => 'Estamos en mantenimiento',
            self::Rain => 'Servicio suspendido por lluvia',
            self::OffHours => 'Fuera de horario de servicio',
        };
    }

    public function body(): string
    {
        return match ($this) {
            self::Maintenance => 'Ups, lo sentimos. Estamos trabajando para ofrecerte un mejor servicio. Volveremos lo antes posible.',
            self::Rain => 'Por la seguridad de nuestros repartidores en moto, suspendemos temporalmente las entregas debido a la lluvia intensa.',
            self::OffHours => 'Por ahora hemos cerrado operaciones. Regresa mañana a partir de las 9:00 a.m. para realizar tu pedido.',
        };
    }

    public function footnote(): string
    {
        return match ($this) {
            self::Maintenance => 'Puedes explorar menús y promociones, pero por el momento no es posible realizar pedidos nuevos.',
            self::Rain => 'Puedes ver el menú y planear tu pedido, pero no podrás ordenar hasta que las condiciones mejoren.',
            self::OffHours => 'Puedes consultar menús y promociones, pero no es posible crear pedidos nuevos fuera de horario.',
        };
    }

    public function activeOrderMessage(): string
    {
        return match ($this) {
            self::Maintenance => 'Si ya tienes un pedido en curso, no te preocupes: lo seguiremos atendiendo y sí te llegará.',
            self::Rain => 'Tu pedido en curso sí te llegará. Es posible que demore un poco más de lo habitual por las condiciones del clima.',
            self::OffHours => 'Si ya tienes un pedido en camino, no te preocupes: sí te llegará. Revisa el estado abajo.',
        };
    }

    public function imagePath(): string
    {
        return match ($this) {
            self::Maintenance => '/images/activity-suspension/maintenance.svg',
            self::Rain => '/images/activity-suspension/rain.svg',
            self::OffHours => '/images/activity-suspension/off-hours.svg',
        };
    }

    public function orderingBlockedMessage(): string
    {
        return match ($this) {
            self::Maintenance => 'Por el momento no podemos recibir pedidos nuevos. Estamos en mantenimiento.',
            self::Rain => 'Por el momento no podemos recibir pedidos nuevos debido a la lluvia intensa.',
            self::OffHours => 'Por el momento no podemos recibir pedidos: estamos fuera de horario de servicio.',
        };
    }
}
