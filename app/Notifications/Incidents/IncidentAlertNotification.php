<?php

namespace App\Notifications\Incidents;

use App\Enums\NotificationCategory;
use App\Enums\NotificationPriority;
use App\Models\Incident;
use App\Notifications\RideNotification;

final class IncidentAlertNotification extends RideNotification
{
    public function __construct(public Incident $incident) {}

    public function category(): NotificationCategory
    {
        return NotificationCategory::Incident;
    }

    public function title(): string
    {
        return 'Incidencia importante';
    }

    public function body(): string
    {
        $orderNumber = $this->incident->order?->order_number;

        return $orderNumber !== null
            ? "Nueva incidencia en el pedido #{$orderNumber}."
            : 'Hay una incidencia que requiere revisión.';
    }

    public function priority(): NotificationPriority
    {
        return NotificationPriority::High;
    }

    public function isCritical(): bool
    {
        return true;
    }

    public function dedupeKey(): ?string
    {
        return 'incident:'.$this->incident->id;
    }

    public function targetType(): ?string
    {
        return 'incident';
    }

    public function targetId(): ?int
    {
        return $this->incident->id;
    }

    public function clickPath(): ?string
    {
        return '/admin/incidents/'.$this->incident->id;
    }
}
